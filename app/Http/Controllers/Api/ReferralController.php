<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\Student;
use Illuminate\Http\Request;

class ReferralController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Referral::with(['student', 'referredBy', 'assignedTo'])
            ->where('is_archived', false)
            ->when($request->status,   fn($q) => $q->where('status', $request->status))
            ->when($request->urgency,  fn($q) => $q->where('urgency_level', $request->urgency))
            ->when($request->type,     fn($q) => $q->where('referral_type', $request->type))
            ->when($request->search,   fn($q) => $q->whereHas('student', fn($s) =>
                $s->where('first_name', 'like', "%{$request->search}%")
                  ->orWhere('last_name', 'like', "%{$request->search}%")
                  ->orWhere('student_id', 'like', "%{$request->search}%")
            ));

        // Faculty only see their own referrals
        if ($user->isFaculty() || $user->isDeanSecretary()) {
            $query->where('referred_by_user_id', $user->id);
        }

        return response()->json($query->latest()->paginate(20));
    }

    public function archived(Request $request)
    {
        $query = Referral::with(['student', 'referredBy'])
            ->where('is_archived', true)
            ->when($request->search, fn($q) => $q->whereHas('student', fn($s) =>
                $s->where('first_name', 'like', "%{$request->search}%")
                  ->orWhere('last_name', 'like', "%{$request->search}%")
                  ->orWhere('student_id', 'like', "%{$request->search}%")
            ));

        return response()->json($query->latest()->paginate(20));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'student_id'          => 'required|exists:students,id',
            'referral_type' => 'required|in:class_attendance,counseling,academic_deficiency,leave_of_absence,withdrawal,readmission,shifting,psychological_testing,disciplinary',
            'nature_of_concern'   => 'required|string|min:10',
            'urgency_level'       => 'required|in:low,medium,high,critical',
            'is_self_referred'    => 'boolean',
            'referrer_source'     => 'nullable|string',
            'violation_type'      => 'nullable|string',
            'incident_description'=> 'nullable|string',
            'incident_date'       => 'nullable|date',
        ]);

        $user = $request->user();
        $student = Student::findOrFail($validated['student_id']);

        // Check if student is new or existing (has prior referrals)
        $isExisting = Referral::where('student_id', $student->id)->exists();

        $referral = Referral::create([
            ...$validated,
            'referred_by_user_id' => $user->id,
            'referrer_name'       => $user->name,
            'referrer_role'       => $user->role,
            'referrer_college'    => $user->college,
            'status'              => 'submitted',
        ]);

        AuditLog::record('created', "Submitted referral {$referral->referral_code} for student {$student->student_id}.", $referral);

        return response()->json([
            ...$referral->load(['student', 'referredBy'])->toArray(),
            'client_status' => $isExisting ? 'existing' : 'new',
        ], 201);
    }

    public function show(Referral $referral)
    {
        $this->authorizeView($referral, request()->user());
        AuditLog::record('viewed', "Viewed referral {$referral->referral_code}.", $referral);

        $priorCount = Referral::where('student_id', $referral->student_id)
            ->where('id', '!=', $referral->id)
            ->count();

        return response()->json([
            ...$referral->load(['student', 'referredBy', 'assignedTo', 'case'])->toArray(),
            'client_status' => $priorCount > 0 ? 'existing' : 'new',
            'prior_referral_count' => $priorCount,
        ]);
    }

    public function update(Request $request, Referral $referral)
    {
        $old = $referral->toArray();
        $referral->update($request->only([
            'nature_of_concern',
            'urgency_level',
            'intake_notes',
            'referral_type',
            'violation_type',
            'incident_description',
            'incident_date',
        ]));
        AuditLog::record('updated', "Updated referral {$referral->referral_code}.", $referral, $old, $referral->toArray());
        return response()->json($referral);
    }

    public function archive(Request $request, Referral $referral)
    {
        $referral->update(['is_archived' => true]);
        AuditLog::record('archived', "Archived referral {$referral->referral_code}.", $referral);
        return response()->json($referral);
    }

    public function unarchive(Request $request, Referral $referral)
    {
        $referral->update(['is_archived' => false]);
        AuditLog::record('unarchived', "Restored referral {$referral->referral_code} from archive.", $referral);
        return response()->json($referral);
    }

    public function acknowledge(Request $request, Referral $referral)
    {
        $referral->update([
            'status'                  => 'acknowledged',
            'acknowledged_at'         => now(),
            'acknowledged_by_user_id' => $request->user()->id,
        ]);

        $case = CaseFile::create([
            'student_id'          => $referral->student_id,
            'referral_id'         => $referral->id,
            'case_type'           => $referral->referral_type,
            'current_unit'        => 'GCU',
            'status'              => 'open',
            'opened_date'         => today(),
            'primary_counselor_id'=> $request->user()->id,
            'presenting_concern'  => $referral->nature_of_concern,
            'is_recurring'        => $referral->student->isRecurring(),
        ]);

        $referral->update(['status' => 'in_review']);

        AuditLog::record('acknowledged', "Acknowledged referral {$referral->referral_code} and created case {$case->case_number}.", $referral);

        return response()->json([
            'referral' => $referral,
            'case'     => $case,
        ]);
    }

    public function assign(Request $request, Referral $referral)
    {
        $request->validate(['user_id' => 'required|exists:users,id']);
        $old = $referral->only(['assigned_to_user_id']);

        $referral->update([
            'assigned_to_user_id' => $request->user_id,
            'assigned_at'         => now(),
        ]);

        AuditLog::record('assigned', "Assigned referral {$referral->referral_code} to user #{$request->user_id}.", $referral, $old);
        return response()->json($referral->load('assignedTo'));
    }

    public function updateStatus(Request $request, Referral $referral)
    {
        $request->validate([
            'status' => 'required|in:submitted,acknowledged,in_review,scheduled,in_progress,referred_tmdu,referred_external,completed,closed'
        ]);
        $old = ['status' => $referral->status];
        $referral->update(['status' => $request->status]);
        AuditLog::record('status_updated', "Updated referral {$referral->referral_code} status to {$request->status}.", $referral, $old);
        return response()->json($referral);
    }

    public function tracking(Referral $referral)
    {
        $this->authorizeView($referral, request()->user());
        return response()->json([
            'referral'     => $referral->only(['referral_code', 'status', 'referral_type', 'created_at', 'acknowledged_at']),
            'case'         => $referral->case?->only(['case_number', 'status', 'total_sessions', 'last_session_at']),
            'appointments' => $referral->case?->appointments()->select('appointment_date', 'start_time', 'status', 'appointment_type')->get(),
        ]);
    }

    private function authorizeView(Referral $referral, $user): void
    {
        if ($user->isFaculty() && $referral->referred_by_user_id !== $user->id) {
            abort(403, 'Unauthorized.');
        }
    }
}