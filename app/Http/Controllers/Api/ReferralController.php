<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\Student;
use App\Models\User;
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
            return;
        }

        if (!$referral
            || $referral->referral_type !== 'psychological_testing'
            || optional($referral->case)->current_unit !== 'TMDU') {
            abort(403, 'TMDU can only modify psychological testing referrals assigned to TMDU.');
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
        $query->whereHas('student', fn($s) => $s->where('college', $user->college));
    } elseif ($user->isTMDUStaff()) {
        $query->where('referral_type', 'psychological_testing')
              ->whereHas('case', fn($c) => $c->where('current_unit', 'TMDU'));
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
            $query->whereHas('student', fn($s) => $s->where('college', $user->college));
        } elseif ($user->isTMDUStaff()) {
            $query->where('referral_type', 'psychological_testing')
                  ->whereHas('case', fn($c) => $c->where('current_unit', 'TMDU'));
        }

        $query->when($request->search, fn($q) => $q->whereHas('student', fn($s) =>
            $s->where('first_name', 'like', "%{$request->search}%")
              ->orWhere('last_name', 'like', "%{$request->search}%")
              ->orWhere('student_id', 'like', "%{$request->search}%")
        ));

        return response()->json($query->latest()->paginate(20));
    }

    public function store(Request $request)
    {
        if ($request->user()->isTMDUStaff()) {
            abort(403, 'TMDU cannot create referrals.');
        }

        $validated = $request->validate([
            'student_id'          => 'required|exists:students,id',
            'referral_type'       => 'required|in:class_attendance,counseling,academic_deficiency,leave_of_absence,withdrawal,readmission,shifting,psychological_testing,disciplinary',
            'nature_of_concern'   => 'required|string|min:10',
            'is_self_referred'    => 'boolean',
            'referrer_source'     => 'nullable|string',
            'violation_type'      => 'nullable|string',
            'incident_description'=> 'nullable|string',
            'incident_date'       => 'nullable|date',
        ]);

        $user = $request->user();
        $student = Student::findOrFail($validated['student_id']);

        if (!$student->is_active) {
            return response()->json(['message' => 'This student account is deactivated and cannot be referred. Please reactivate the student first.'], 422);
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

        $sduTypes  = ['disciplinary'];
        $tmduTypes = ['psychological_testing'];
        $unitRoles = ['admin'];
        if (in_array($validated['referral_type'], $sduTypes)) {
            $unitRoles[] = 'sdu_head';
        } elseif (in_array($validated['referral_type'], $tmduTypes)) {
            $unitRoles[] = 'tmdu_staff';
        } else {
            $unitRoles[] = 'gcu_staff';
        }

        $recipients = User::whereIn('role', $unitRoles)->where('is_active', true)->get();
        $collegeRep = User::where('role', 'dean_secretary')->where('college', $student->college)->where('is_active', true)->get();
        $recipients = $recipients->merge($collegeRep)->unique('id');
        Notification::send($recipients, new NewReferralNotification($referral));

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
            // A "Refer to TMDU" creates a separate, sibling Referral row
            // (referral_type psychological_testing) on this same case - see
            // CaseController::referToTmdu(). Loading its TestingRecord (and
            // the PAR document attached to it) here is what lets this GCU
            // referral's own SIF page surface the PAR results once TMDU has
            // issued them, without needing to open the Testing Record itself.
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
        $this->authorizeReferralWriter($request, $referral);
        $this->authorizeView($referral, $request->user());
        $old = $referral->toArray();
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

        $referral->update(['status' => 'in_review']);
        $referral->refresh();

        // A GCU/SDU referral gets a self-schedulable "Initial Counseling"
        // slot - the student picks their own date/time via a scheduling
        // link, and staff confirms it from the Appointments queue. A TMDU
        // testing referral does NOT get this at all: TMDU sets the actual
        // test-taking date/time directly on the Testing Record Details page
        // (TestingRecordController::scheduleTesting()), with no appointment
        // of any kind created here. Reuse whatever's already
        // pending/confirmed for this case instead of piling up duplicate
        // appointments, for the GCU/SDU case.
        $appointment     = null;
        $schedulingLink  = null;

        if (!$isTmduTesting) {
            $appointmentType = 'initial_counseling';

            $appointment = $case->appointments()
                ->where('appointment_type', $appointmentType)
                ->where('unit', $unit)
                ->whereNotIn('status', ['cancelled', 'completed', 'no_show'])
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
        $notifiables = collect([$referral->student, $referral->referredBy])->filter();
        Notification::send($notifiables, new ReferralAcknowledgedNotification($referral));
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

        $referral->update([
            'assigned_to_user_id' => $request->user_id,
            'assigned_at'         => now(),
        ]);

        AuditLog::record('assigned', "Assigned referral {$referral->referral_code} to user #{$request->user_id}.", $referral, $old);
        return response()->json($referral->load('assignedTo'));
    }

    public function updateStatus(Request $request, Referral $referral)
    {
        $this->authorizeReferralWriter($request, $referral);
        $this->authorizeView($referral, $request->user());
        $request->validate([
            'status' => 'required|in:submitted,acknowledged,in_review,scheduled,in_progress,referred_tmdu,referred_external,completed,closed'
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

        $old = ['status' => $referral->status];
        $referral->update(['status' => $request->status]);
        AuditLog::record('status_updated', "Updated referral {$referral->referral_code} status to {$request->status}.", $referral, $old);

        if ($referral->referredBy) {
            Notification::send($referral->referredBy, new ReferralStatusUpdatedNotification($referral, $request->status));
        }

        return response()->json($referral);
    }

    public function sendFeedback(Request $request, Referral $referral)
    {
        $this->authorizeReferralWriter($request, $referral);
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
            'feedback_notes'               => 'required|string',
            'feedback_checklist'           => 'nullable|array',
            'feedback_checklist.*'         => 'string|in:interview,counseling,psychological_testing,referred_scholarship,referred_other,others',
            'feedback_referred_other_text' => 'nullable|string|max:255',
            'feedback_others_text'         => 'nullable|string|max:255',
            'feedback_ctrl_no'             => 'nullable|string|max:50',
        ]);

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

        $validated = $request->validate([
            'admission_date'     => 'required|date',
            'admission_time_in'  => 'nullable|date_format:H:i',
            'admission_time_out' => 'nullable|date_format:H:i',
            'admission_excused'  => 'nullable|boolean',
            'admission_remarks'  => 'nullable|string',
        ]);

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
        if ($user->isFaculty() && $referral->referred_by_user_id !== $user->id) {
            abort(403, 'Unauthorized.');
        }
        if ($user->isDeanSecretary() && $referral->student->college !== $user->college) {
            abort(403, 'Unauthorized.');
        }

        if ($user->isTMDUStaff()) {
            $isTesting = $referral->referral_type === 'psychological_testing';
            $inTmdu    = optional($referral->case)->current_unit === 'TMDU';

            if (!$isTesting || !$inTmdu) {
                abort(403, 'TMDU can only view psychological testing referrals assigned to TMDU.');
            }
        }
    }
}