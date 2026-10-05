<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CaseFile;
use App\Models\Complaint;
use App\Models\ComplaintAttachment;
use App\Models\Referral;
use App\Models\Student;
use App\Models\User;
use App\Notifications\NewReferralNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

class ComplaintController extends Controller
{
    // Only for plain <input> fields with a constrained, name/label-like
    // shape (not addresses, which legitimately use "#" and "/"; not the
    // <textarea>-backed description, which just gets a max length).
    private const TEXT_REGEX = '/^[a-zA-Z0-9\x{00C0}-\x{024F}\'\-\.\,\&\(\)\s]+$/u';

    public function index(Request $request)
    {
        $query = Complaint::with(['complainee', 'filedBy'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('complaint_code', 'like', "%{$search}%")
                  ->orWhereHas('complainee', function ($sq) use ($search) {
                      $sq->where('first_name', 'like', "%{$search}%")
                         ->orWhere('last_name', 'like', "%{$search}%")
                         ->orWhere('student_id', 'like', "%{$search}%");
                  });
            });
        }

        return $query->paginate(20);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'complainant_name'       => ['required', 'string', 'max:255', 'regex:' . self::TEXT_REGEX],
            'complainant_address'    => 'required|string|max:255',
            'complainee_student_id'  => 'required|exists:students,id',
            'complainee_position'    => ['nullable', 'string', 'max:255', 'regex:' . self::TEXT_REGEX],
            'complainee_college'     => 'nullable|string|max:255',
            'complainee_department'  => 'nullable|string|max:255',
            'complainee_office'      => ['nullable', 'string', 'max:255', 'regex:' . self::TEXT_REGEX],
            'complainee_address'     => 'nullable|string|max:255',
            'violation_type'         => 'required|string|max:255',
            'incident_date'          => 'required|date',
            'description'            => 'required|string|max:2000',
            'certification_agreed'   => 'required|accepted',
            'evidence.*'             => 'nullable|file|max:10240',
            'affidavit.*'            => 'nullable|file|max:10240',
        ]);

        $student = Student::findOrFail($validated['complainee_student_id']);
        if (! $student->is_active) {
            return response()->json(['message' => 'This student record is inactive.'], 422);
        }

        $complaint = Complaint::create([
            ...$validated,
            'certification_agreed' => true,
            'filed_by_user_id'     => $request->user()->id,
            'status'                => 'pending',
        ]);

        foreach (['evidence', 'affidavit'] as $category) {
            foreach ($request->file($category, []) as $file) {
                $path = $file->store('complaints/' . $complaint->id, 'public');
                ComplaintAttachment::create([
                    'complaint_id'       => $complaint->id,
                    'category'           => $category,
                    'file_path'          => $path,
                    'original_filename'  => $file->getClientOriginalName(),
                ]);
            }
        }

        // Filing a complaint is a disciplinary referral in substance, so it
        // needs to land in the Referral Queue and route to the SDU Head the
        // same way a regular disciplinary referral would (mirrors
        // ReferralController::store()).
        $case = CaseFile::where('student_id', $student->id)->first();

        if (!$case) {
            $case = CaseFile::create([
                'student_id'   => $student->id,
                'case_type'    => 'disciplinary',
                'current_unit' => 'GCU',
                'status'       => 'open',
                'opened_date'  => today(),
            ]);
        } elseif (!$case->isOpen()) {
            $case->update(['status' => 'open', 'closed_date' => null]);
        }

        $referral = Referral::create([
            'student_id'           => $student->id,
            'case_id'              => $case->id,
            'complaint_id'         => $complaint->id,
            'referral_type'        => 'disciplinary',
            'nature_of_concern'    => $validated['description'],
            'urgency_level'        => 'medium',
            'is_self_referred'     => false,
            'referrer_source'      => $request->user()->role,
            'violation_type'       => $validated['violation_type'],
            'incident_description' => $validated['description'],
            'incident_date'        => $validated['incident_date'],
            'referred_by_user_id'  => $request->user()->id,
            'referrer_name'        => $request->user()->name,
            'referrer_role'        => $request->user()->role,
            'referrer_college'     => $request->user()->college,
            'status'               => 'submitted',
        ]);

        AuditLog::record('created', "Filed complaint {$complaint->complaint_code} against student {$student->student_id}, logged as referral {$referral->referral_code}.", $referral);

        $recipients = User::whereIn('role', ['admin', 'sdu_head'])->where('is_active', true)->get();
        $collegeRep = User::where('role', 'dean_secretary')->where('college', $student->college)->where('is_active', true)->get();
        $recipients = $recipients->merge($collegeRep)->unique('id');
        Notification::send($recipients, new NewReferralNotification($referral));

        return $complaint->load(['complainee', 'filedBy', 'attachments']);
    }

    public function show(Complaint $complaint)
    {
        return $complaint->load(['complainee', 'filedBy', 'attachments']);
    }

    public function updateStatus(Request $request, Complaint $complaint)
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,under_review,resolved',
        ]);

        $complaint->update($validated);

        return $complaint->load(['complainee', 'filedBy', 'attachments']);
    }
}