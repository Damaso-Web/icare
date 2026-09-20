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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class ReferralController extends Controller
{
    public function index(Request $request)
{
    $user = $request->user();

    $sortDirection = $request->sort === 'asc' ? 'asc' : 'desc';

    $sduTypes  = ['disciplinary'];
    $tmduTypes = ['psychological_testing'];

    $query = Referral::with(['student', 'referredBy', 'assignedTo'])
        ->where('is_archived', $request->boolean('archived'))
        ->when($request->status,          fn($q) => $q->where('status', $request->status))
        ->when($request->urgency,         fn($q) => $q->where('urgency_level', $request->urgency))
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
    }

    return response()->json(
        $query->orderByRaw("CASE WHEN status IN ('completed','closed') THEN 1 ELSE 0 END ASC")
            ->orderBy('created_at', $sortDirection)
            ->paginate(20)
    );
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
            'referral_type'       => 'required|in:class_attendance,counseling,academic_deficiency,leave_of_absence,withdrawal,readmission,shifting,psychological_testing,disciplinary',
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
        $this->authorizeView($referral, $request->user());
        $old = $referral->toArray();
        $referral->update($request->only([
            'nature_of_concern',
            'urgency_level',
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
        $this->authorizeView($referral, $request->user());
        $referral->update(['is_archived' => true]);
        AuditLog::record('archived', "Archived referral {$referral->referral_code}.", $referral);
        return response()->json($referral);
    }

    public function unarchive(Request $request, Referral $referral)
    {
        $this->authorizeView($referral, $request->user());
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

    $unit = match ($referral->referral_type) {
        'disciplinary'          => 'SDU',
        'psychological_testing' => 'TMDU',
        default                 => 'GCU',
    };

    // The case was already opened (or reused) when the referral was
    // submitted - acknowledging just means staff is now picking it up.
    // Fall back to the student's existing case (not a brand-new one) for
    // referrals that somehow reached this point without a case_id, since a
    // student may only ever have one case.
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

    // A case can have several referrals, and each one gets acknowledged
    // separately - but they shouldn't each spawn their own generic "Initial
    // Counseling" slot. Reuse whatever's already pending/confirmed for this
    // case instead of piling up duplicate appointments.
    $appointment = $case->appointments()
        ->where('appointment_type', 'initial_counseling')
        ->where('unit', 'GCU')
        ->whereNotIn('status', ['cancelled', 'completed', 'no_show'])
        ->latest()
        ->first();

    if ($appointment) {
        $schedulingLink = $appointment->request_status === 'awaiting_student'
            ? url("/schedule/{$appointment->scheduling_token}")
            : null;
    } else {
        $token = \Illuminate\Support\Str::random(48);

        // This is just a placeholder slot until the student picks their own
        // date/time via the scheduling link - but it's still a real row with
        // a real date, so it must never land on a weekend by default.
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
            'appointment_type'    => 'initial_counseling',
            'unit'                => 'GCU',
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

    AuditLog::record('acknowledged', "Acknowledged referral {$referral->referral_code} under case {$case->case_number}.", $referral);

    $notifiables = collect([$referral->student, $referral->referredBy])->filter();
    Notification::send($notifiables, new ReferralAcknowledgedNotification($referral));

    return response()->json([
        'referral'          => $referral,
        'case'              => $case,
        'appointment'       => $appointment,
        'scheduling_link'   => $schedulingLink,
    ]);
}

    public function assign(Request $request, Referral $referral)
    {
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
        $this->authorizeView($referral, $request->user());
        $request->validate([
            'status' => 'required|in:submitted,acknowledged,in_review,scheduled,in_progress,referred_tmdu,referred_external,completed,closed'
        ]);
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
        $user = $request->user();
        if (!$user->canCounsel()) {
            abort(403, 'Access denied.');
        }

        $validated = $request->validate([
            'feedback_notes' => 'required|string',
        ]);

        $referral->update([
            'feedback_notes'           => $validated['feedback_notes'],
            'feedback_sent_at'         => now(),
            'feedback_sent_by_user_id' => $user->id,
        ]);

        AuditLog::record('feedback_sent', "Sent feedback slip for referral {$referral->referral_code} to referrer.", $referral);

        return response()->json($referral->load('feedbackSentBy'));
    }

    public function saveAdmissionSlip(Request $request, Referral $referral)
    {
        $user = $request->user();
        if (!$user->canCounsel()) {
            abort(403, 'Access denied.');
        }

        if ($referral->referral_type !== 'class_attendance') {
            abort(422, 'Admission slips only apply to class attendance referrals.');
        }

        $validated = $request->validate([
            'admission_date'     => 'required|date',
            'admission_time_in'  => 'nullable|date_format:H:i',
            'admission_time_out' => 'nullable|date_format:H:i',
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
    }
}