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
use App\Notifications\FillOutFormsNotification;
use App\Notifications\NewComplaintNotification;
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
        $query = Complaint::with(['complainee', 'filedBy', 'filedByStudent']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('violation_type')) {
            $query->where('violation_type', $request->violation_type);
        }
        // "Filed By" comes as u:<staff id> or s:<student id>.
        if ($request->filled('filed_by') && preg_match('/^([us]):(\d+)$/', $request->filed_by, $m)) {
            $query->where($m[1] === 'u' ? 'filed_by_user_id' : 'filed_by_student_id', (int) $m[2]);
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

        $this->applySort($query, $request);

        $filers = [];
        foreach (User::whereIn('id', Complaint::whereNotNull('filed_by_user_id')->select('filed_by_user_id'))->orderBy('name')->get(['id', 'name']) as $u) {
            $filers[] = ['value' => 'u:' . $u->id, 'label' => $u->name];
        }
        foreach (Student::whereIn('id', Complaint::whereNotNull('filed_by_student_id')->select('filed_by_student_id'))->orderBy('last_name')->get(['id', 'first_name', 'last_name']) as $st) {
            $filers[] = ['value' => 's:' . $st->id, 'label' => trim($st->first_name . ' ' . $st->last_name) . ' (student)'];
        }

        return array_merge($query->paginate(20)->toArray(), [
            'filter_options' => [
                'violation_types' => $this->distinctTypes(Complaint::query()),
                'filers'          => $filers,
            ],
        ]);
    }

    // Newest first unless the caller asks for oldest first.
    private function applySort($query, Request $request): void
    {
        $request->input('sort') === 'oldest'
            ? $query->orderBy('created_at')->orderBy('id')
            : $query->orderByDesc('created_at')->orderByDesc('id');
    }

    private function distinctTypes($query): array
    {
        return $query->whereNotNull('violation_type')->distinct()->orderBy('violation_type')->pluck('violation_type')->all();
    }

    // Search / Type / Status / Sort shared by the two "Sent Complaints" lists.
    private function sentList($base, Request $request)
    {
        // All acts of misconduct set up in Management and every complaint status, not only those already in use (B491).
        $configured = \App\Models\ReferralFormOption::where('category', 'act_of_misconduct')->orderBy('sort_order')->pluck('label')->all();
        $options = [
            'violation_types' => collect($configured)->merge($this->distinctTypes(clone $base))->unique()->filter()->values()->all(),
            'statuses'        => collect(['pending', 'under_review', 'resolved'])
                ->merge((clone $base)->whereNotNull('status')->distinct()->pluck('status'))->unique()->values()->all(),
        ];
        $query = $base->with(['complainee', 'filedBy', 'filedByStudent']);
        if ($request->filled('status'))         $query->where('status', $request->status);
        if ($request->filled('violation_type')) $query->where('violation_type', $request->violation_type);
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('complaint_code', 'like', "%{$search}%")
                  ->orWhereHas('complainee', fn($sq) => $sq->where('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%")
                      ->orWhere('student_id', 'like', "%{$search}%"));
            });
        }
        $this->applySort($query, $request);
        return array_merge($query->paginate(20)->toArray(), ['filter_options' => $options]);
    }

    public function store(Request $request)
    {
        $u = $request->user();
        return $this->file($request, [
            'user_id' => $u->id, 'student_id' => null, 'name' => $u->name,
            'role' => $u->role, 'college' => $u->college,
        ]);
    }

    // A student files a complaint against another student from the portal.
    public function storeByStudent(Request $request)
    {
        $me = $request->user('student');
        abort_if((int) $request->complainee_student_id === (int) $me->id, 422, 'You cannot file a complaint against yourself.');
        $request->merge(['complainant_name' => trim($me->first_name . ' ' . $me->last_name)]);
        // Student form: Narration of Facts is 10 to 1000 characters.
        // Bug (10/29/2026): evidence and the Affidavit of Witness were both
        // required - they're an either/or: either one attached is enough,
        // checked manually below instead of two separate "required" rules.
        $fileRule = 'file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240';
        $request->validate([
            'description'  => ['required', 'string', 'min:10', 'max:1000', 'regex:' . \App\Support\PlainText::REQUIRES_LETTER_REGEX],
            'evidence'     => 'nullable|array',
            'evidence.*'   => $fileRule,
            'affidavit'    => 'nullable|array',
            'affidavit.*'  => $fileRule,
        ], [
            'description.min'    => 'Please describe the facts in at least 10 characters.',
            // B584: Narration of Facts must not be numbers-only.
            'description.regex'  => 'Please provide a valid narration of facts. Numbers alone are not allowed.',
            'evidence.*.mimes'   => 'Evidence must be a PDF, Word document (DOC, DOCX) or image (JPG, PNG).',
            'affidavit.*.mimes'  => 'Affidavit of Witness must be a PDF, Word document (DOC, DOCX) or image (JPG, PNG).',
        ]);
        if (!count($request->file('evidence', [])) && !count($request->file('affidavit', []))) {
            abort(422, 'Please attach at least one evidence file or at least one Affidavit of Witness file.');
        }
        return $this->file($request, [
            'user_id' => null, 'student_id' => $me->id, 'name' => trim($me->first_name . ' ' . $me->last_name),
            'role' => 'student', 'college' => $me->college,
        ]);
    }

    // Complaints the signed-in staff account has filed.
    public function mine(Request $request)
    {
        return $this->sentList(Complaint::where('filed_by_user_id', $request->user()->id), $request);
    }

    public function showMine(Request $request, Complaint $complaint)
    {
        abort_unless((int) $complaint->filed_by_user_id === (int) $request->user()->id, 403);
        return $this->presentMine($complaint);
    }

    public function mineByStudent(Request $request)
    {
        return $this->sentList(Complaint::where('filed_by_student_id', $request->user('student')->id), $request);
    }

    public function showMineByStudent(Request $request, Complaint $complaint)
    {
        abort_unless((int) $complaint->filed_by_student_id === (int) $request->user('student')->id, 403);
        return $this->presentMine($complaint);
    }

    private function presentMine(Complaint $complaint)
    {
        $complaint->load(['complainee', 'filedBy', 'filedByStudent', 'attachments']);
        $complaint->attachments->each->append('url');
        return $complaint;
    }

    private function file(Request $request, array $filer)
    {
        // Staff attach the evidence and/or the affidavit of a witness - an
        // either/or, checked manually below; students file from their own
        // portal form (storeByStudent() already validated/checked theirs).
        $staff = $filer['user_id'] !== null;
        $proofRules = $staff
            ? ['evidence' => 'nullable|array', 'affidavit' => 'nullable|array']
            : [];
        $validated = $request->validate([
            ...$proofRules,
            'complainant_name'       => ['required', 'string', 'max:255', 'regex:' . self::TEXT_REGEX],
            'complainant_address'    => ['required', 'string', 'max:255', 'regex:' . \App\Support\PlainText::REQUIRES_LETTER_REGEX],
            'complainee_student_id'  => 'required|exists:students,id',
            'complainee_position'    => ['nullable', 'string', 'max:255', 'regex:' . self::TEXT_REGEX],
            'complainee_college'     => 'nullable|string|max:255',
            'complainee_department'  => 'nullable|string|max:255',
            'complainee_office'      => ['nullable', 'string', 'max:255', 'regex:' . self::TEXT_REGEX],
            'complainee_address'     => 'nullable|string|max:255',
            'violation_type'         => 'required|string|max:255',
            'incident_date'          => [
                'required',
                'date',
                'before_or_equal:today',
                'after_or_equal:' . now()->subYear()->toDateString(),
            ],
            'description'            => ['required', 'string', 'max:2000', 'regex:' . \App\Support\PlainText::REQUIRES_LETTER_REGEX],
            'certification_agreed'   => 'required|accepted',
            'evidence.*'             => 'nullable|file|max:10240',
            'affidavit.*'            => 'nullable|file|max:10240',
        ], [
            // B584: Address and Narration of Facts must not be numbers-only.
            'complainant_address.regex' => 'Please provide a valid address. Numbers alone are not allowed.',
            'description.regex'         => 'Please provide a valid narration of facts. Numbers alone are not allowed.',
            'incident_date.before_or_equal' => 'The date of incident cannot be in the future.',
            'incident_date.after_or_equal'  => 'The date of incident cannot be more than one year in the past.',
        ]);

        if ($staff && !count($request->file('evidence', [])) && !count($request->file('affidavit', []))) {
            abort(422, 'Please attach at least one evidence file or at least one Affidavit of Witness file.');
        }

        $student = Student::findOrFail($validated['complainee_student_id']);
        if (! $student->is_active) {
            return response()->json(['message' => 'This student record is inactive.'], 422);
        }

        // B590: a Dean files complaints for their own college - enforced
        // here too, not only by what the search box returns.
        $actor = $request->user();
        if ($actor && method_exists($actor, 'isDean') && $actor->isDean()
            && !in_array($student->college, \App\Support\CollegeName::equivalents($actor->college), true)) {
            return response()->json(['message' => 'You can only file complaints against students enrolled in your own college.'], 403);
        }

        // Bug (10/14/2026): same reasoning as the Dean check above, scoped
        // one level further - a Dept Chair files complaints for their own
        // department only.
        if ($actor && method_exists($actor, 'isDeptChair') && $actor->isDeptChair() && (
            !in_array($student->college, \App\Support\CollegeName::equivalents($actor->college), true)
            || !\App\Support\DepartmentScope::matches($student->program, $actor->department)
        )) {
            return response()->json(['message' => 'You can only file complaints against students enrolled in your own department.'], 403);
        }

        // Bug (10/29/2026): the student-portal complaint form
        // (FileComplaint.vue) never asked for complainee_college/
        // department/address at all - only the staff "File a Complaint"
        // form (Create.vue) fills those in client-side from the selected
        // student. $student is already loaded above for the Dean/Dept
        // Chair scope checks, so default any of these three the request
        // didn't send from that same record instead of leaving them null
        // for every student-filed complaint.
        $validated['complainee_college'] = $validated['complainee_college'] ?? $student->college;
        $validated['complainee_address'] = $validated['complainee_address'] ?? $student->address;
        if (empty($validated['complainee_department'])) {
            $linkedDepartment = \App\Models\Program::where('name', $student->program)->with('department')->first()?->department?->name;
            if ($linkedDepartment) {
                $validated['complainee_department'] = $linkedDepartment;
            }
        }

        $complaint = Complaint::create([
            ...$validated,
            'certification_agreed' => true,
            'filed_by_user_id'     => $filer['user_id'],
            'filed_by_student_id'  => $filer['student_id'],
            'status'                => 'pending',
        ]);

        foreach (['evidence', 'affidavit'] as $category) {
            foreach ($request->file($category, []) as $file) {
                // Keep the file in the database (base64) so it survives backend
                // redeploys; Render's free disk is wiped each deploy.
                ComplaintAttachment::create([
                    'complaint_id'       => $complaint->id,
                    'category'           => $category,
                    'file_path'          => '',
                    'original_filename'  => $file->getClientOriginalName(),
                    'mime_type'          => $file->getMimeType(),
                    'content'            => base64_encode(file_get_contents($file->getRealPath())),
                ]);
            }
        }

        // Filing a complaint is a disciplinary referral in substance, so it
        // needs to land in the Referral Queue and route to the SDU Head the
        // same way a regular disciplinary referral would (mirrors
        // ReferralController::store()).
        $case = CaseFile::where('student_id', $student->id)->first();
        $newCase = !$case;

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
            'referrer_source'      => $filer['role'],
            'violation_type'       => $validated['violation_type'],
            'incident_description' => $validated['description'],
            'incident_date'        => $validated['incident_date'],
            'referred_by_user_id'  => $filer['user_id'],
            'referred_by_student_id' => $filer['student_id'],
            'referrer_name'        => $filer['name'],
            'referrer_role'        => $filer['role'],
            'referrer_college'     => $filer['college'],
            'status'               => 'submitted',
        ]);

        AuditLog::record('created', "Filed complaint {$complaint->complaint_code} against student {$student->student_id}, logged as referral {$referral->referral_code}.", $referral);

        // A complaint is always logged as a disciplinary referral, SDU's
        // jurisdiction - 'admin' (the GCU Head) is a GCU-scoped role, not a
        // system-wide one, so it isn't included here.
        $recipients = User::whereIn('role', ['sdu_head'])->where('is_active', true)->get();
        $collegeRep = User::whereIn('role', ['dean', 'dean_secretary'])->where('college', $student->college)->where('is_active', true)->get();
        $recipients = $recipients->merge($collegeRep)->unique('id');
        Notification::send($recipients, new NewComplaintNotification($complaint));

        // The complaint opened this student's Student Information File.
        if ($newCase) {
            $student->notify(new FillOutFormsNotification());
        }

        return $complaint->load(['complainee', 'filedBy', 'filedByStudent', 'attachments']);
    }

    public function show(Complaint $complaint)
    {
        $complaint->load(['complainee', 'filedBy', 'filedByStudent', 'attachments']);
        // The download link is an accessor, so it has to be asked for.
        $complaint->attachments->each->append('url');

        return $complaint;
    }

    // Opens one evidence / affidavit file. The old link pointed at
    // /storage/..., which only works when the storage symlink exists (it never
    // did on the deployed server, so every preview failed) and left the file
    // open to anyone with the URL. This streams it to the SDU Head, the staff
    // member who filed the complaint, or whoever is allowed to view the
    // referral this complaint was filed together with (e.g. the GCU
    // counselor or Dean it was sent to) - referrals/Show.vue renders the
    // same attachments inside the referral's Incident Report card.
    public function attachment(Request $request, Complaint $complaint, ComplaintAttachment $attachment)
    {
        abort_unless((int) $attachment->complaint_id === (int) $complaint->id, 404);

        $user = $request->user();
        if (!$user->isSDUHead() && !$user->isAdmin() && (int) $complaint->filed_by_user_id !== (int) $user->id) {
            $referral = $complaint->referral;
            abort_unless($referral, 403, 'Unauthorized.');
            app(ReferralController::class)->authorizeView($referral, $user);
        }

        // The file is stored in the database (base64) so it survives redeploys.
        abort_if(empty($attachment->content), 404, 'This file is no longer available. Ask the sender to upload it again.');

        $filename = str_replace(['"', "\r", "\n"], '', $attachment->original_filename ?: 'attachment');

        return response(base64_decode($attachment->content))
            ->header('Content-Type', $attachment->mime_type ?: 'application/octet-stream')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->header('X-Content-Type-Options', 'nosniff');
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