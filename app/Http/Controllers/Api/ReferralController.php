<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\Student;
use App\Models\User;
use App\Notifications\ReferredStudentNotification;
use App\Notifications\NewReferralNotification;
use App\Notifications\ReferralAcknowledgedNotification;
use App\Notifications\ReferralStatusUpdatedNotification;
use App\Notifications\TestingAppointmentReadyNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class ReferralController extends Controller
{
    // Locks TMDU staff to only modifying psychological-testing referrals
    // that are currently assigned to TMDU's own unit - they can't write to
    // any other referral type, or to a testing referral that hasn't (or no
    // longer) belongs to TMDU (e.g. still sitting with GCU pre-acknowledge).
    private function authorizeReferralWriter(Request $request, ?Referral $referral = null): void
    {
        $user = $request->user();

        if (!$user->isTMDUStaff()) {
            // GCU can never act on TMDU's own Case Referral Slip.
            if ($referral && $referral->isTmduOwned()) {
                abort(403, 'This Case Referral Slip belongs to TMDU.');
            }
            return;
        }

        if (!$referral || !$referral->isTmduOwned()) {
            abort(403, 'TMDU can only modify its own Case Referral Slips.');
        }
    }

    public function index(Request $request)
{
    $user = $request->user();

    $sortDirection = $request->sort === 'asc' ? 'asc' : 'desc';

    $sduTypes  = ['disciplinary'];
    $tmduTypes = ['psychological_testing'];

    $query = Referral::with(['student', 'referredBy', 'assignedTo'])
        ->where('is_archived', $request->boolean('archived'))
        // Complaints (Incident Reports) are SDU's own module - they're filed
        // and tracked through the dedicated Complaints page, not the general
        // Referral Queue. A referral originated from a Complaint always has
        // complaint_id set (see ComplaintController::store()), so excluding
        // those here keeps the Queue to ordinary GCU/TMDU/SDU referrals only.
        ->whereNull('complaint_id')
        // Dean: narrow the college's referrals by the referring faculty's
        // department, then by the student's program.
        ->when($request->department, fn($q) => $q->whereHas('referredBy', fn($u) => $u->where('department', $request->department)))
        ->when($request->program,    fn($q) => $q->whereHas('student', fn($st) => $st->where('program', $request->program)))
        ->when($request->status,          fn($q) => $q->where('status', $request->status))
        ->when($request->type,            fn($q) => $q->where('referral_type', $request->type))
        ->when($request->violation_type,  fn($q) => $q->where('referral_type', 'disciplinary')->where('violation_type', $request->violation_type))
        ->when($request->unit && !$request->violation_type, function ($q) use ($request, $sduTypes, $tmduTypes) {
            if ($request->unit === 'SDU') {
                $q->whereIn('referral_type', $sduTypes);
            } elseif ($request->unit === 'TMDU') {
                $q->whereIn('referral_type', $tmduTypes);
            } elseif ($request->unit === 'GCU') {
                $q->whereNotIn('referral_type', array_merge($sduTypes, $tmduTypes));
            }
        })
        ->when($request->date_from, fn($q) => $q->whereDate('created_at', '>=', $request->date_from))
        ->when($request->date_to,   fn($q) => $q->whereDate('created_at', '<=', $request->date_to))
        ->when($request->search, fn($q) => $q->whereHas('student', fn($s) =>
            $s->where('first_name', 'like', "%{$request->search}%")
              ->orWhere('last_name', 'like', "%{$request->search}%")
              ->orWhere('student_id', 'like', "%{$request->search}%")
        ));

    if ($user->isFaculty()) {
        $query->where('referred_by_user_id', $user->id);
    } elseif ($user->isDeanSecretary()) {
        // A college representative coordinates on behalf of their whole
        // college, not just referrals they personally submitted.
        $query->whereHas('student', fn($s) => $s->whereIn('college', \App\Support\CollegeName::equivalents($user->college)));
    } elseif ($user->isTMDUStaff()) {
        // TMDU only ever sees the Case Referral Slips GCU sent to it.
        $query->tmduOwned();
    }

    // Everyone else (GCU, faculty, college reps, SDU) never sees TMDU's
    // Case Referral Slips - those live in TMDU's own queue.
    if (!$user->isTMDUStaff()) {
        $query->notTmduOwned();
    }

    return response()->json(
        $query->orderByRaw("CASE WHEN status IN ('completed','closed') THEN 1 ELSE 0 END ASC")
            ->orderBy('created_at', $sortDirection)
            ->paginate(20)
    );
}

    public function archived(Request $request)
    {
        $user  = $request->user();
        $query = Referral::with(['student', 'referredBy'])
            ->where('is_archived', true)
            // Same exclusion as index() - Complaints stay out of the Referral
            // Queue's archive view too.
            ->whereNull('complaint_id');

        if ($user->isFaculty()) {
            $query->where('referred_by_user_id', $user->id);
        } elseif ($user->isDeanSecretary()) {
            $query->whereHas('student', fn($s) => $s->whereIn('college', \App\Support\CollegeName::equivalents($user->college)));
        } elseif ($user->isTMDUStaff()) {
            $query->tmduOwned();
        }

        if (!$user->isTMDUStaff()) {
            $query->notTmduOwned();
        }

        $query->when($request->search, fn($q) => $q->whereHas('student', fn($s) =>
            $s->where('first_name', 'like', "%{$request->search}%")
              ->orWhere('last_name', 'like', "%{$request->search}%")
              ->orWhere('student_id', 'like', "%{$request->search}%")
        ));

        return response()->json($query->latest()->paginate(20));
    }

    /** The referral source that goes with an account's role (the office it files from). */
    public static function sourceForRole(?string $role): string
    {
        return [
            'dept_chair' => 'faculty',
            'sdu_head'   => 'sdu',
            'tmdu_staff' => 'tmdu',
            'tmdu_head'  => 'tmdu',
            'dean_secretary' => 'dean',
        ][$role] ?? ($role ?: 'faculty');
    }

    // Referrals the signed-in account has sent (Sent Referrals page).
    public function mine(Request $request)
    {
        return self::sentList(
            Referral::where('referred_by_user_id', $request->user()->id)->whereNull('complaint_id'),
            $request
        );
    }

    // Search / Type / Status / Sort for a "Sent Referrals" list (staff and student).
    public static function sentList($base, Request $request)
    {
        // Every service GCU offers and every status a referral can have, not only the ones already in use (B490).
        $serviceOptions = \App\Models\ReferralFormOption::where('category', 'referral_type')->orderBy('sort_order')->get(['value', 'label']);
        $usedTypes      = (clone $base)->distinct()->pluck('referral_type')->all();
        $types = $serviceOptions->pluck('value')->merge($usedTypes)->unique()->filter()->values()->all();
        $allStatuses = ['submitted', 'acknowledged', 'scheduled', 'in_progress', 'referred_tmdu', 'referred_external', 'completed', 'closed'];
        $usedStatuses = (clone $base)->distinct()->pluck('status')->all();
        $options = [
            'types'        => $types,
            'type_labels'  => $serviceOptions->pluck('label', 'value')->all(),
            'statuses'     => collect($allStatuses)->merge($usedStatuses)->unique()->filter()->values()->all(),
        ];
        $q = $base->with('student:id,student_id,first_name,last_name,college');
        // B597: the Dean dashboard's metric cards link here with a status
        // group (e.g. every "in progress" status after acknowledgement), not
        // just one value, so a comma list is accepted alongside a single status.
        if ($request->filled('status')) {
            $statuses = array_values(array_filter(array_map('trim', explode(',', (string) $request->status))));
            count($statuses) > 1 ? $q->whereIn('status', $statuses) : $q->where('status', $statuses[0] ?? $request->status);
        }
        if ($request->filled('referral_type')) $q->where('referral_type', $request->referral_type);
        if ($request->filled('search')) {
            $search = $request->search;
            $q->where(function ($w) use ($search) {
                $w->where('referral_code', 'like', "%{$search}%")
                  ->orWhereHas('student', fn($s) => $s->where('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%")
                      ->orWhere('student_id', 'like', "%{$search}%"));
            });
        }
        $request->input('sort') === 'oldest'
            ? $q->orderBy('created_at')->orderBy('id')
            : $q->orderByDesc('created_at')->orderByDesc('id');
        return array_merge($q->paginate(20)->toArray(), ['filter_options' => $options]);
    }

    // The sender corrects a referral that GCU has not acknowledged yet.
    public function updateMine(Request $request, $id)
    {
        $referral = Referral::where('referred_by_user_id', $request->user()->id)->whereNull('complaint_id')->findOrFail($id);
        $referral->applyReferrerEdit($request);
        return response()->json($referral->load('student:id,student_id,first_name,last_name,college'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'student_id'          => 'required|exists:students,id',
            'referral_type'       => 'required|in:class_attendance,counseling,academic_deficiency,leave_of_absence,withdrawal,readmission,shifting,psychological_testing,disciplinary',
            'nature_of_concern'   => ['required', 'string', 'min:10', 'max:1000', 'regex:' . \App\Support\PlainText::REQUIRES_LETTER_REGEX],
            'is_self_referred'    => 'boolean',
            'referrer_source'     => 'nullable|string|max:255',
            'violation_type'      => 'nullable|string|max:255',
            'incident_description'=> 'nullable|string|max:2000',
            'incident_date'       => 'nullable|date',
        ], [
            // B581: "Numbers alone" covers both pure digits and digits-plus-punctuation.
            'nature_of_concern.regex' => 'Please provide a valid referral reason. Numbers alone are not allowed.',
        ]);

        $user = $request->user();
        $student = Student::findOrFail($validated['student_id']);

        if (!$student->is_active) {
            return response()->json(['message' => 'This student account is deactivated and cannot be referred. Please reactivate the student first.'], 422);
        }

        // B590: a Dean refers for their own college - enforced here too, not
        // only by what the search box returns, in case a student_id from
        // outside their college reaches this endpoint some other way.
        if ($user->isDean() && !in_array($student->college, \App\Support\CollegeName::equivalents($user->college), true)) {
            return response()->json(['message' => 'You can only refer students enrolled in your own college.'], 403);
        }

        // Bug (10/14/2026): a Dept Chair acts for their own department only -
        // same reasoning as the Dean check above, scoped one level further
        // (college AND program/department), and enforced here too in case a
        // student_id from outside it reaches this endpoint some other way.
        if ($user->isDeptChair() && (
            !in_array($student->college, \App\Support\CollegeName::equivalents($user->college), true)
            || strcasecmp((string) $student->program, (string) $user->department) !== 0
        )) {
            return response()->json(['message' => 'You can only refer students enrolled in your own department.'], 403);
        }

        // A student has exactly one case file for life. New referral -> new
        // case if they've never had one; existing referral -> attach to the
        // case they already have (and reopen it if it had been resolved).
        $case = CaseFile::where('student_id', $student->id)->first();
        $isExisting = (bool) $case;

        if (!$case) {
            $case = CaseFile::create([
                'student_id'   => $student->id,
                'case_type'    => $validated['referral_type'],
                'current_unit' => 'GCU',
                'status'       => 'open',
                'opened_date'  => today(),
            ]);
        } elseif (!$case->isOpen()) {
            $case->update(['status' => 'open', 'closed_date' => null]);
        }

        // The source of a referral is who filed it - taken from the account,
        // not chosen on the form.
        $validated['referrer_source']  = self::sourceForRole($user->role);
        $validated['is_self_referred'] = false;

        $referral = Referral::create([
            ...$validated,
            'case_id'             => $case->id,
            'referred_by_user_id' => $user->id,
            'referrer_name'       => $user->name,
            'referrer_role'       => $user->role,
            'referrer_college'    => $user->college,
            'status'              => 'submitted',
        ]);

        AuditLog::record('created', "Submitted referral {$referral->referral_code} for student {$student->student_id}.", $referral);

        // 'admin' is the GCU Head - GCU-scoped, not a system-wide role (that's
        // system_admin) - so it's only notified about GCU referrals, same as
        // gcu_staff. SDU/TMDU referrals go to their own unit only.
        $sduTypes  = ['disciplinary'];
        $tmduTypes = ['psychological_testing'];
        if (in_array($validated['referral_type'], $sduTypes)) {
            $unitRoles = ['sdu_head'];
        } elseif (in_array($validated['referral_type'], $tmduTypes)) {
            $unitRoles = ['tmdu_staff', 'tmdu_head'];
        } else {
            $unitRoles = ['admin', 'gcu_staff'];
        }

        $recipients = User::whereIn('role', $unitRoles)->where('is_active', true)->get();
        $collegeRep = User::whereIn('role', ['dean', 'dean_secretary'])->where('college', $student->college)->where('is_active', true)->get();
        $recipients = $recipients->merge($collegeRep)->unique('id');
        Notification::send($recipients, new NewReferralNotification($referral));

        // Tell the student they were referred (who, which unit, their login)
        // and ask them to update My Account. Sent on every referral.
        $student->notify(new ReferredStudentNotification($referral));

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

        $referral->load([
            'student', 'referredBy', 'assignedTo', 'feedbackSentBy', 'admissionIssuedBy',
            'case.handoffs.fromUser', 'case.handoffs.toUser',
            'case.interventions.personInCharge', 'case.interventions.recordedBy', 'case.interventions.referral', 'case.interventions.completedBy',
            'case.counselor', 'case.referrals', 'case.appointments.staff',
            'case.parentConferenceSlips.issuedBy',
            'case.parentConferenceSlips.notesRecordedBy',
            // A "Refer to TMDU" creates a separate, sibling Referral row
            // (referral_type psychological_testing) on this same case - see
            // CaseController::referToTmdu(). Loading its TestingRecord (and
            // the PAR document attached to it) here is what lets this GCU
            // referral's own SIF page surface the PAR results once TMDU has
            // issued them, without needing to open the Testing Record itself.
            'testingRecord',
            'case.referrals.testingRecord.documents',
            'case.referrals.testingRecord.tester',
            // For a disciplinary referral filed together with a Complaint
            // ("Incident Report"), pull in the full complaint + its evidence
            // so referrals/Show.vue can render it in place of the normal
            // Referral Info panel once acknowledged.
            'complaint.complainee', 'complaint.filedBy', 'complaint.attachments',
            'appointments',
            // Immutable Feedback Slip send history, newest first.
            'feedbackSlips.sentBy',
        ]);

        $relatedConcerns = $referral->case
            ? $referral->case->referrals
                ->groupBy('referral_type')
                ->map(fn($group, $type) => ['type' => $type, 'count' => $group->count()])
                ->values()
            : [];

        return response()->json([
            ...$referral->toArray(),
            'client_status' => $priorCount > 0 ? 'existing' : 'new',
            'prior_referral_count' => $priorCount,
            'related_concerns' => $relatedConcerns,
        ]);
    }

    public function update(Request $request, Referral $referral)
    {
        // The sender (any role: GCU, SDU, TMDU, faculty...) may correct their own
        // referral until it is acknowledged - even a TMDU slip GCU sent.
        if ($referral->referred_by_user_id && (int) $referral->referred_by_user_id === (int) $request->user()->id
            && ! $request->hasAny(['intake_notes', 'sanction', 'sanction_notes'])) {
            $referral->abortIfLocked();
            $referral->applyReferrerEdit($request);
            return response()->json($referral->fresh());
        }

        $this->authorizeReferralWriter($request, $referral);
        $this->authorizeView($referral, $request->user());
        $referral->abortIfLocked();
        $old = $referral->toArray();

        // The person who submitted a referral can correct its details only until
        // GCU acknowledges it. After that it is GCU's record.
        $user = $request->user();
        if ($user->isFaculty() || $user->isDeanSecretary()) {
            abort_unless($referral->referred_by_user_id === $user->id, 403, 'Only the person who submitted this referral can edit it.');
            abort_if($referral->acknowledged_at, 422, 'GCU already acknowledged this referral, so it can no longer be edited.');

            $data = $request->validate([
                'nature_of_concern'    => ['sometimes', 'required', 'string', 'min:10', 'max:1000', 'regex:' . \App\Support\PlainText::REQUIRES_LETTER_REGEX],
                'incident_description' => 'sometimes|nullable|string|max:2000',
                'incident_date'        => 'sometimes|nullable|date',
            ], [
                'nature_of_concern.regex' => 'Please provide a valid referral reason. Numbers alone are not allowed.',
            ]);
            $referral->update($data);
            AuditLog::record('updated', "Referrer edited referral {$referral->referral_code} before acknowledgement.", $referral, $old, $referral->toArray());
            return response()->json($referral);
        }

        $referral->update($request->only([
            'nature_of_concern',
            'intake_notes',
            'referral_type',
            'violation_type',
            'incident_description',
            'incident_date',
            'sanction',
            'sanction_notes',
        ]));
        AuditLog::record('updated', "Updated referral {$referral->referral_code}.", $referral, $old, $referral->toArray());
        return response()->json($referral);
    }

    public function archive(Request $request, Referral $referral)
    {
        $this->authorizeReferralWriter($request, $referral);
        $this->authorizeView($referral, $request->user());
        $referral->update(['is_archived' => true]);
        AuditLog::record('archived', "Archived referral {$referral->referral_code}.", $referral);
        return response()->json($referral);
    }

    public function unarchive(Request $request, Referral $referral)
    {
        $this->authorizeReferralWriter($request, $referral);
        $this->authorizeView($referral, $request->user());
        $referral->update(['is_archived' => false]);
        AuditLog::record('unarchived', "Restored referral {$referral->referral_code} from archive.", $referral);
        return response()->json($referral);
    }

    public function acknowledge(Request $request, Referral $referral)
{
    $this->authorizeReferralWriter($request, $referral);
    if ($referral->is_archived) {
        abort(422, 'This referral is archived and can no longer be edited.');
    }

    // "Psychological Testing" is also a selectable Service Requested tag on
    // the general referral form (used SWS-wide for counting/filtering by
    // service type), so referral_type alone does NOT mean this is a TMDU
    // testing cycle - every referral must still go through the normal GCU
    // interview first. A referral only truly becomes a TMDU testing cycle
    // once it carries its own TestingRecord, which only happens when it was
    // created via CaseController::referToTmdu() (GCU explicitly escalating
    // after their own interview). That record tracks the rest of the
    // workflow (schedule test taking -> test administered -> awaiting
    // results -> results released), so acknowledging such a referral skips
    // the generic initial-counseling appointment entirely (TMDU schedules
    // the actual test date/time directly on the Testing Record), and moves
    // the TestingRecord into its next stage.
    $isTmduTesting = $referral->testingRecord()->exists();

    // Previously this required a psychometrician (TMDU staff/head) to have
    // already claimed the testing record via TestingRecordController::
    // assign() before the referral itself could even be acknowledged.
    // Acknowledging only records that GCU/TMDU has picked the referral up -
    // it shouldn't be blocked on a separate staffing step. Assigning a
    // tester is still done the same way, any time, from the Testing Record
    // Details page; it just no longer has to happen first.

    // Everything below - the referral/case updates, the appointment
    // creation, and the audit log entry - must succeed or fail together.
    // Previously these ran as separate, uncommitted-transaction writes, so
    // if a later step failed (e.g. the appointment insert hitting a DB
    // constraint it didn't satisfy), the referral had already been flipped
    // to "acknowledged"/"in_review" and committed - the request still came
    // back as a 500, but a page refresh showed it as already acknowledged.
    // Wrapping it all in one transaction means a failure now rolls
    // everything back, so a 500 always means nothing was saved.
    [$referral, $case, $appointment, $schedulingLink] = DB::transaction(function () use ($request, $referral, $isTmduTesting) {
        $referral->update([
            'status'                  => 'acknowledged',
            'acknowledged_at'         => now(),
            'acknowledged_by_user_id' => $request->user()->id,
        ]);

        // Same reasoning as $isTmduTesting above: the case only moves to
        // TMDU when this referral is a real testing cycle (has a
        // TestingRecord), never just because "Psychological Testing" was
        // picked as the Service Requested tag on a general referral.
        $unit = match (true) {
            $referral->referral_type === 'disciplinary' => 'SDU',
            $isTmduTesting                               => 'TMDU',
            default                                       => 'GCU',
        };

        // The case was already opened (or reused) when the referral was
        // submitted - acknowledging just means staff is now picking it up.
        // Fall back to the student's existing case (not a brand-new one) for
        // referrals that somehow reached this point without a case_id, since
        // a student may only ever have one case.
        $case = $referral->case
            ?? CaseFile::where('student_id', $referral->student_id)->first()
            ?? CaseFile::create([
                'student_id'   => $referral->student_id,
                'case_type'    => $referral->referral_type,
                'current_unit' => $unit,
                'status'       => 'open',
                'opened_date'  => today(),
            ]);

        if (!$case->isOpen()) {
            $case->update(['status' => 'open', 'closed_date' => null]);
        }

        $case->update([
            'current_unit'         => $unit,
            'primary_counselor_id' => $case->primary_counselor_id ?? $request->user()->id,
            'presenting_concern'   => $case->presenting_concern ?? $referral->nature_of_concern,
            'is_recurring'         => $referral->student->isRecurring(),
        ]);

        if (!$referral->case_id) {
            $referral->update(['case_id' => $case->id]);
        }

        // B275: previously jumped straight to 'in_review' here, before any
        // appointment even existed - confusing, and redundant with
        // 'in_progress'. Status now stays 'acknowledged' until the student's
        // appointment is actually confirmed (-> 'scheduled', set in
        // AppointmentController::confirm()) and then attended (->
        // 'in_progress', set in AppointmentController::checkIn()).
        $referral->refresh();

        // A GCU/SDU referral gets a self-schedulable "Initial Counseling"
        // slot - the student picks their own date/time via a scheduling
        // link, and staff confirms it from the Appointments queue. A TMDU
        // testing referral does NOT get this at all: TMDU sets the actual
        // test-taking date/time directly on the Testing Record Details page
        // (TestingRecordController::scheduleTesting()), with no appointment
        // of any kind created here. Reuse whatever's already pending/
        // confirmed for THIS referral (not just any past referral on the
        // same case) instead of piling up duplicate appointments - scoping
        // by referral_id, not just case+type+unit, matters because a case
        // can have several referrals over time, and reusing an old,
        // already-superseded appointment from a previous referral silently
        // skipped creating a new scheduling link for this one (the referral
        // never showed up for the student to self-schedule). 'rescheduled'
        // appointments are excluded too, since those are superseded rows in
        // a reschedule chain, not the active one.
        $appointment     = null;
        $schedulingLink  = null;

        if (!$isTmduTesting) {
            $appointmentType = \App\Models\Referral::appointmentTypeFor($referral->referral_type);

            $appointment = $case->appointments()
                ->where('referral_id', $referral->id)
                ->where('appointment_type', $appointmentType)
                ->where('unit', $unit)
                ->whereNotIn('status', ['cancelled', 'completed', 'no_show', 'rescheduled'])
                ->latest()
                ->first();

            if ($appointment) {
                $schedulingLink = $appointment->request_status === 'awaiting_student'
                    ? url("/schedule/{$appointment->scheduling_token}")
                    : null;
            } else {
                $token = \Illuminate\Support\Str::random(48);

                // This is just a placeholder slot until the student picks
                // their own date/time via the scheduling link - but it's
                // still a real row with a real date, so it must never land
                // on a weekend by default.
                $placeholderDate = now()->addDay();
                while ($placeholderDate->isWeekend()) {
                    $placeholderDate->addDay();
                }

                $appointment = \App\Models\Appointment::create([
                    'case_id'             => $case->id,
                    'referral_id'         => $referral->id,
                    'student_id'          => $case->student_id,
                    'staff_user_id'       => $request->user()->id,
                    'created_by_user_id'  => $request->user()->id,
                    'appointment_type'    => $appointmentType,
                    'unit'                => $unit,
                    'scheduling_token'    => $token,
                    'token_expires_at'    => now()->addDays(7),
                    'request_status'      => 'awaiting_student',
                    'status'              => 'pending',
                    'appointment_date'    => $placeholderDate->format('Y-m-d'),
                    'start_time'          => '08:00',
                    'end_time'            => '09:00',
                    // Shown to the student on their scheduling page / appointment
                    // details page before they confirm, based on referral type.
                    'required_documents'  => \App\Models\Referral::requiredDocumentsFor($referral->referral_type),
                ]);
                $schedulingLink = url("/schedule/{$token}");
            }
        }

        // Move the linked TestingRecord into its next stage now that TMDU
        // has acknowledged the referral - mirrors what
        // TestingRecordController::acknowledge() does for records without a
        // linked referral (kept for backward compatibility with older
        // data). No fee-form/OR step anymore, so this just confirms the
        // record is in the (already-default) "pending" stage, ready for
        // Schedule Test Taking.
        if ($isTmduTesting && $referral->testingRecord) {
            $referral->testingRecord->update(['status' => 'pending']);
        }

        AuditLog::record('acknowledged', "Acknowledged referral {$referral->referral_code} under case {$case->case_number}.", $referral);

        return [$referral, $case, $appointment, $schedulingLink];
    });

    // Notifications happen after the transaction commits - no point
    // notifying anyone about a change that could still have rolled back.
    if ($isTmduTesting && $referral->testingRecord) {
        // The testing-specific notification just tells the student TMDU
        // will reach out to arrange the test-taking schedule - no
        // scheduling link, since TMDU sets that date directly. The generic
        // one still goes to whoever referred the case to TMDU.
        if ($referral->student) {
            Notification::send($referral->student, new TestingAppointmentReadyNotification($referral->testingRecord));
        }
        if ($referral->referredBy) {
            Notification::send($referral->referredBy, new ReferralAcknowledgedNotification($referral));
        }
    } else {
        // The acknowledgment goes to whoever filed the referral, not to the
        // student (the student is told about the referral when it is created).
        // A self-referral has no staff referrer, so the student is the referrer.
        $referrer = $referral->referredBy ?? $referral->student;
        if ($referrer) {
            Notification::send($referrer, new ReferralAcknowledgedNotification($referral));
        }
    }

    return response()->json([
        'referral'          => $referral,
        'case'              => $case,
        'appointment'       => $appointment,
        'scheduling_link'   => $schedulingLink,
    ]);
}

    public function assign(Request $request, Referral $referral)
    {
        $this->authorizeReferralWriter($request, $referral);
        $this->authorizeView($referral, $request->user());
        $request->validate(['user_id' => 'required|exists:users,id']);
        $old = $referral->only(['assigned_to_user_id']);
        $previousId = $referral->assigned_to_user_id;

        $referral->update([
            'assigned_to_user_id' => $request->user_id,
            'assigned_at'         => now(),
        ]);

        // The receiving staff member and the student are told in the app.
        if ((int) $previousId !== (int) $request->user_id) {
            $receiver = User::find($request->user_id);
            if ($receiver && $receiver->id !== $request->user()->id) {
                $receiver->notify(new \App\Notifications\ReferralAssignedNotification($referral, $request->user()->name));
            }
            if ($referral->student) {
                $referral->student->notify(new \App\Notifications\CaseUpdateNotification(
                    "Your referral {$referral->referral_code} has been assigned to " . ($receiver?->name ?? 'another staff member') . '.',
                    $referral->id, $referral->case_id, $referral->referral_code
                ));
            }

            // The one who sent the referral (staff or student) is told too.
            $referrer = $referral->referred_by_user_id
                ? User::find($referral->referred_by_user_id)
                : ($referral->referred_by_student_id ? \App\Models\Student::find($referral->referred_by_student_id) : null);
            $isStudentTarget = $referrer instanceof \App\Models\Student && $referrer->id === $referral->student_id;
            $isStaffSkip = $referrer instanceof User && in_array($referrer->id, [$request->user()->id, (int) $request->user_id], true);
            if ($referrer && ! $isStudentTarget && ! $isStaffSkip) {
                $referrer->notify(new \App\Notifications\CaseUpdateNotification(
                    "The referral {$referral->referral_code} you sent has been reassigned to " . ($receiver?->name ?? 'another staff member') . '.',
                    $referral->id, $referral->case_id, $referral->referral_code
                ));
            }
        }

        AuditLog::record('assigned', "Assigned referral {$referral->referral_code} to user #{$request->user_id}.", $referral, $old);
        return response()->json($referral->load('assignedTo'));
    }

    public function updateStatus(Request $request, Referral $referral)
    {
        $this->authorizeReferralWriter($request, $referral);
        $this->authorizeView($referral, $request->user());
        $request->validate([
            'status' => 'required|in:submitted,acknowledged,scheduled,in_progress,referred_tmdu,referred_external,completed,closed'
        ]);

        // "Resolve Referral" goes through this same endpoint (status =
        // completed) - the referral must be acknowledged AND have an
        // attended appointment before the SIF can be marked done (same
        // broader gate the rest of the SIF now enforces - see
        // Referral::canEditSif()). Only gates that one target status; every
        // other status move in the normal workflow is unaffected.
        if ($request->status === 'completed' && !$referral->canEditSif()) {
            abort(422, 'This referral must be acknowledged and have an appointment set and attended before it can be resolved.');
        }
        if ($request->status === 'completed' && !$referral->sessionNotes()->exists()) {
            abort(422, 'Add at least one session note before resolving this referral.');
        }
        if ($referral->is_archived) {
            abort(422, 'This referral is archived and can no longer be edited.');
        }

        // Statuses cannot go backward - see Referral::canMoveStatusTo().
        if (!$referral->canMoveStatusTo($request->status)) {
            abort(422, 'Referral status cannot move backward.');
        }

        $old = ['status' => $referral->status];
        $referral->update(['status' => $request->status]);
        AuditLog::record('status_updated', "Updated referral {$referral->referral_code} status to {$request->status}.", $referral, $old);

        if ($referral->referredBy) {
            Notification::send($referral->referredBy, new ReferralStatusUpdatedNotification($referral, $request->status));
        }

        return response()->json($referral);
    }

    /**
     * Makes sure an acknowledged GCU/SDU referral has its "waiting for the student to pick a
     * schedule" appointment (shown in Appointments, with the scheduling link on the student's
     * side). Does nothing for TMDU testing cycles, which TMDU schedules on the Testing Record.
     */
    private function ensureSchedulingAppointment(Referral $referral, User $actor): void
    {
        if ($referral->testingRecord()->exists()) {
            return;
        }

        $unit = $referral->referral_type === 'disciplinary' ? 'SDU' : 'GCU';
        $case = $referral->case
            ?? CaseFile::where('student_id', $referral->student_id)->first()
            ?? CaseFile::create([
                'student_id'   => $referral->student_id,
                'case_type'    => $referral->referral_type,
                'current_unit' => $unit,
                'status'       => 'open',
                'opened_date'  => today(),
            ]);
        if (!$referral->case_id) {
            $referral->update(['case_id' => $case->id]);
        }
        if (!$case->isOpen()) {
            $case->update(['status' => 'open', 'closed_date' => null]);
        }
        $case->update([
            'current_unit'         => $unit,
            'primary_counselor_id' => $case->primary_counselor_id ?? $actor->id,
        ]);

        $type = \App\Models\Referral::appointmentTypeFor($referral->referral_type);
        $existing = $case->appointments()
            ->where('referral_id', $referral->id)
            ->where('appointment_type', $type)
            ->where('unit', $unit)
            ->whereNotIn('status', ['cancelled', 'completed', 'no_show', 'rescheduled'])
            ->exists();
        if ($existing) {
            return;
        }

        $placeholder = now()->addDay();
        while ($placeholder->isWeekend()) {
            $placeholder->addDay();
        }
        \App\Models\Appointment::create([
            'case_id'            => $case->id,
            'referral_id'        => $referral->id,
            'student_id'         => $case->student_id,
            'staff_user_id'      => $referral->assigned_to_user_id ?? $case->primary_counselor_id ?? $actor->id,
            'created_by_user_id' => $actor->id,
            'appointment_type'   => $type,
            'unit'               => $unit,
            'scheduling_token'   => \Illuminate\Support\Str::random(48),
            'token_expires_at'   => now()->addDays(7),
            'request_status'     => 'awaiting_student',
            'status'             => 'pending',
            'appointment_date'   => $placeholder->format('Y-m-d'),
            'start_time'         => '08:00',
            'end_time'           => '09:00',
            'required_documents' => \App\Models\Referral::requiredDocumentsFor($referral->referral_type),
        ]);
    }

    // The GCU Head can set any referral's status by hand, backward too, with a
    // reason. Meant for correcting mistakes; every change is in the audit log.
    public function overrideStatus(Request $request, Referral $referral)
    {
        $this->authorizeView($referral, $request->user());
        $data = $request->validate([
            'status' => 'required|in:submitted,acknowledged,scheduled,in_progress,completed,closed',
            'reason' => ['required', 'string', 'min:5', 'max:255', 'regex:/\p{L}/u'],
        ], ['reason.regex' => 'Please explain the reason in words, not numbers only.']);
        if ($referral->is_archived) {
            abort(422, 'This referral is archived. Restore it before changing its status.');
        }
        if ($data['status'] === $referral->status) {
            abort(422, 'The referral already has this status.');
        }

        $old = ['status' => $referral->status];
        $changes = ['status' => $data['status']];
        // Moving it back before "acknowledged" also clears the acknowledgement.
        if ($data['status'] === 'submitted') {
            $changes['acknowledged_at'] = null;
        } elseif (!$referral->acknowledged_at) {
            $changes['acknowledged_at'] = now();
        }
        $referral->update($changes);

        // Setting a referral to "Acknowledged" by hand must also hand it to the
        // Appointments queue and the student, exactly like the normal Acknowledge button.
        if ($data['status'] === 'acknowledged') {
            $this->ensureSchedulingAppointment($referral->fresh(), $request->user());
        }

        if ($referral->student) {
            $referral->student->notify(new \App\Notifications\CaseUpdateNotification(
                "The status of your referral {$referral->referral_code} was updated to " . ucwords(str_replace('_', ' ', $data['status'])) . '.',
                $referral->id, $referral->case_id, $referral->referral_code
            ));
        }

        AuditLog::record('status_overridden', "GCU Head changed referral {$referral->referral_code} from {$old['status']} to {$data['status']}. Reason: {$data['reason']}", $referral, $old, ['status' => $data['status']]);

        return response()->json(['message' => 'Referral status updated.', 'referral' => $referral->fresh()]);
    }

    // The person who referred the student asks GCU for the Feedback Slip once
    // the referral is completed. Once a day at most, and not after it was sent.
    public function requestFeedback(Request $request, Referral $referral)
    {
        $user = $request->user();
        abort_unless($referral->referred_by_user_id === $user->id, 403, 'Only the person who submitted this referral can ask for its feedback slip.');
        abort_unless(in_array($referral->status, ['completed', 'closed'], true), 422, 'The feedback slip can be requested once the referral is completed.');
        abort_if($referral->feedbackSlips()->exists(), 422, 'The feedback slip for this referral was already sent to you.');

        $asked = AuditLog::where('action', 'feedback_requested')
            ->where('model_type', Referral::class)->where('model_id', $referral->id)
            ->where('created_at', '>=', now()->subDay())->exists();
        abort_if($asked, 422, 'You already asked for this feedback slip today. GCU has been notified.');

        $recipients = $referral->case?->counselor ? collect([$referral->case->counselor]) : collect();
        $recipients = $recipients->merge(User::whereIn('role', ['admin', 'gcu_staff'])->where('is_active', true)->get())->unique('id');
        Notification::send($recipients, new \App\Notifications\FeedbackRequestedNotification($referral, $user->name));

        AuditLog::record('feedback_requested', "{$user->name} asked for the Feedback Slip of referral {$referral->referral_code}.", $referral);

        return response()->json(['message' => 'Request sent. GCU will send you the feedback slip.']);
    }

    public function sendFeedback(Request $request, Referral $referral)
    {
        $this->authorizeReferralWriter($request, $referral);
        $referral->abortIfLocked();
        $user = $request->user();
        if (!$user->canCounsel()) {
            abort(403, 'Access denied.');
        }

        // The referral must be acknowledged AND have an attended
        // appointment before a Feedback Slip can go out - previously this
        // only checked for an attended appointment, without requiring the
        // referral to have actually been acknowledged first.
        if (!$referral->canEditSif()) {
            abort(422, 'This referral must be acknowledged and have an appointment set and attended before a feedback slip can be sent.');
        }

        $validated = $request->validate([
            'feedback_notes'               => 'required|string|max:3000',
            'feedback_checklist'           => 'required|array|min:1',
            'feedback_checklist.*'         => 'string|in:interview,counseling,psychological_testing,referred_scholarship,referred_other,others',
            'feedback_referred_other_text' => 'nullable|string|max:255',
            'feedback_others_text'         => 'nullable|string|max:255',
            'feedback_ctrl_no'             => 'nullable|string|max:50',
        ], [
            'feedback_checklist.required' => 'Select at least one Intervention/s or Assistance Provided.',
            'feedback_checklist.min'      => 'Select at least one Intervention/s or Assistance Provided.',
        ]);

        // "Referred to other" / "Others" need their text once ticked.
        foreach (['referred_other' => 'feedback_referred_other_text', 'others' => 'feedback_others_text'] as $item => $field) {
            if (in_array($item, $validated['feedback_checklist'], true) && !trim((string) ($validated[$field] ?? ''))) {
                abort(422, 'Please specify the ' . ($item === 'others' ? 'other intervention or assistance provided.' : 'referral under Intervention/s or Assistance Provided.'));
            }
        }

        // Every send is its own immutable history row - like a session note,
        // never overwritten - snapshotting who it was sent to (the referrer
        // as recorded at submission time) alongside who recorded/sent it.
        $slip = $referral->feedbackSlips()->create([
            ...$validated,
            'sent_by_user_id' => $user->id,
            'sent_to_name'    => $referral->referrer_name,
            'sent_to_role'    => $referral->referrer_role,
            'sent_at'         => now(),
        ]);

        // Kept in sync for anything still reading the single-slip columns
        // (e.g. the printed slip's "last sent" line), but the history list
        // in feedback_slips is now the source of truth.
        $referral->update([
            ...$validated,
            'feedback_sent_at'         => now(),
            'feedback_sent_by_user_id' => $user->id,
        ]);

        AuditLog::record('feedback_sent', "Sent feedback slip for referral {$referral->referral_code} to referrer.", $referral);

        return response()->json($slip->load('sentBy'));
    }

    public function saveAdmissionSlip(Request $request, Referral $referral)
    {
        $this->authorizeReferralWriter($request, $referral);
        $referral->abortIfLocked();
        $user = $request->user();
        if (!$user->canCounsel()) {
            abort(403, 'Access denied.');
        }

        if ($referral->referral_type !== 'class_attendance') {
            abort(422, 'Admission slips only apply to class attendance referrals.');
        }

        // Same broader SIF edit gate as the rest of the referral - the
        // admission slip can't be issued or changed until the referral has
        // been acknowledged AND an appointment has been attended.
        if (!$referral->canEditSif()) {
            abort(422, 'This referral must be acknowledged and have an appointment set and attended before an admission slip can be issued.');
        }

        // Once marked Unexcused, the admission slip locks - no further
        // changes, same "no take-backs" rule the old case-intervention-based
        // excused lock enforced (CaseInterventionController::store()),
        // just checked on the referral's own admission_excused column now.
        if ($referral->admission_excused === false) {
            abort(422, 'This referral has been marked Unexcused. No further changes can be made to the admission slip.');
        }

        // Same items as the paper slip (QF-OSS-GCU-09).
        $causes  = ['health', 'personal', 'filial', 'socio_cultural', 'environmental', 'behavioral', 'official_activity', 'others'];
        $actions = ['verified_documents', 'interviewed', 'life_coaching', 'parent_conference', 'counseling', 'others'];
        $validated = $request->validate([
            'admission_date'     => 'required|date',
            'admission_time_in'  => 'nullable|date_format:H:i',
            'admission_time_out' => 'nullable|date_format:H:i|after:admission_time_in',
            'admission_excused'  => 'required|boolean',
            'admission_remarks'  => 'nullable|string|max:1000',
            'admission_details'                => 'required|array',
            'admission_details.days_count'     => 'nullable|string|max:50',
            'admission_details.causes'         => 'required|array|min:1',
            'admission_details.causes.*'       => 'in:' . implode(',', $causes),
            'admission_details.cause_activity' => 'nullable|string|max:255',
            'admission_details.cause_others'   => 'nullable|string|max:255',
            'admission_details.actions'        => 'required|array|min:1',
            'admission_details.actions.*'      => 'in:' . implode(',', $actions),
            'admission_details.action_others'  => 'nullable|string|max:255',
        ], [
            'admission_excused.required'         => 'Mark the slip as Excused or Unexcused.',
            'admission_details.causes.required'  => 'Tick at least one cause of absence.',
            'admission_details.actions.required' => 'Tick at least one action taken.',
            'admission_time_out.after'           => 'The end time must be after the start time.',
        ]);
        $d = $validated['admission_details'];
        if (in_array('official_activity', $d['causes'], true) && !trim((string) ($d['cause_activity'] ?? ''))) {
            abort(422, 'Name the official co/extra-curricular activity.');
        }
        if (in_array('others', $d['causes'], true) && !trim((string) ($d['cause_others'] ?? ''))) {
            abort(422, 'Write the other cause of absence.');
        }
        if (in_array('others', $d['actions'], true) && !trim((string) ($d['action_others'] ?? ''))) {
            abort(422, 'Write the other action taken.');
        }

        $referral->update([
            ...$validated,
            'admission_issued_at'         => now(),
            'admission_issued_by_user_id' => $user->id,
        ]);

        AuditLog::record('admission_slip_issued', "Issued admission slip for referral {$referral->referral_code}.", $referral);

        return response()->json($referral->load('admissionIssuedBy'));
    }

    public function tracking(Referral $referral)
    {
        $this->authorizeReferralWriter(request(), $referral);
        $this->authorizeView($referral, request()->user());
        return response()->json([
            'referral'     => $referral->only(['referral_code', 'status', 'referral_type', 'created_at', 'acknowledged_at']),
            'case'         => $referral->case?->only(['case_number', 'status', 'total_sessions', 'last_session_at']),
            'appointments' => $referral->case?->appointments()->select('appointment_date', 'start_time', 'status', 'appointment_type')->get(),
        ]);
    }

    private function authorizeView(Referral $referral, $user): void
    {
        // Whoever sent a referral can always open it.
        if ($referral->referred_by_user_id && (int) $referral->referred_by_user_id === (int) $user->id) {
            return;
        }
        if ($user->isFaculty() && $referral->referred_by_user_id !== $user->id) {
            abort(403, 'Unauthorized.');
        }
        if ($user->isDeanSecretary() && !in_array($referral->student->college, \App\Support\CollegeName::equivalents($user->college), true)) {
            abort(403, 'Unauthorized.');
        }

        $tmduOwned = $referral->isTmduOwned();

        if ($user->isTMDUStaff() && !$tmduOwned) {
            abort(403, 'TMDU can only view its own Case Referral Slips.');
        }
        if (!$user->isTMDUStaff() && $tmduOwned) {
            abort(403, 'This Case Referral Slip belongs to TMDU.');
        }
    }
}