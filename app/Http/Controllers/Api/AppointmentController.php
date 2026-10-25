<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Appointment;
use App\Models\StaffAvailability;
use App\Models\User;
use App\Notifications\AppointmentConfirmedNotification;
use App\Notifications\NoShowEscalationNotification;
use App\Notifications\UnscheduledCallSlipNotification;
use App\Notifications\DocumentsRequiredNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Carbon\Carbon;

class AppointmentController extends Controller
{
    // A reschedule limit any higher stops functioning as a limit at all.
    private const RESCHEDULE_LIMIT = 3;


    // Auto-filled into required_documents on confirm() for a class_attendance
    // referral's appointment, when staff doesn't type their own text -
    // mirrors TestingRecordController::TESTING_REMINDER's pattern for the
    // psychological_testing workflow. Per QF-OSS-GCU-09 (Admission Slip):
    // once the appointment is confirmed, the student needs to be told to
    // bring these so the admission slip can actually be processed.
    private const CLASS_ATTENDANCE_REMINDER = "Please bring your Student ID, a signed Letter of Explanation, a photocopy of the valid ID of the parent/legal guardian who signed the letter, 3 specimen signatures of that same parent/legal guardian, and the relevant supporting documents (e.g., medical certificate, or others that apply).";
    // B300: every service needs the Student ID, so the reminder is automatic
    // for all appointment types, not only class attendance.
    private const GENERAL_REMINDER = "Please bring your Student ID.";
    // TMDU staff only act on TMDU appointments; GCU staff never on TMDU's.
    private function authorizeUnit(Appointment $appointment): void
    {
        $user = request()->user();
        $role = $user?->role;
        if (in_array($role, ['tmdu_staff', 'tmdu_head'], true) && $appointment->unit !== 'TMDU') {
            abort(403, 'This appointment belongs to another unit.');
        }
        if (in_array($role, ['admin', 'gcu_staff'], true) && $appointment->unit === 'TMDU') {
            abort(403, 'This appointment belongs to TMDU.');
        }

        // B625 reverted (10/25/2026): gcu_staff is back to unit-wide access
        // on GCU appointments, same as Student Information Files (B623).
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $perPage = $request->input('per_page', 20);

        return response()->json(
            Appointment::with(['student', 'staff', 'case.latestReferral', 'referral', 'createdBy'])
                ->when($request->date,        fn($q) => $q->whereDate('appointment_date', $request->date))
                ->when($request->unit,        fn($q) => $q->where('unit', $request->unit))
                ->when($request->status, function ($q) use ($request) {
                    if ($request->status === 'rescheduled') {
                        $q->where('status', 'pending')->where('request_status', 'awaiting_student');
                    } elseif (str_contains($request->status, ',')) {
                        $q->whereIn('status', explode(',', $request->status));
                    } else {
                        $q->where('status', $request->status);
                    }
                })
                // The Dashboard's "Appointments Today" count leaves out cancelled, rescheduled and
                // still-waiting-for-the-student requests; active=1 applies the same rule to the list.
                ->when($request->boolean('active'), fn($q) => $q->whereNotIn('status', ['cancelled', 'rescheduled'])
                    ->where(fn($w) => $w->whereNull('request_status')->orWhereNotIn('request_status', ['awaiting_student', 'cancelled', 'rescheduled'])))
                ->when($request->referral_id,      fn($q) => $q->where('referral_id', $request->referral_id))
                ->when($request->appointment_type, fn($q) => $q->where('appointment_type', $request->appointment_type))
                // GCU and TMDU appointments are separate calendars.
                ->when($user->isTMDUStaff(), fn($q) => $q->where('unit', 'TMDU'))
                ->when($user->isSDUHead(),   fn($q) => $q->where('unit', 'SDU'))
                ->when(in_array($user->role, ['admin', 'gcu_staff'], true), fn($q) => $q->where('unit', '!=', 'TMDU')->where('unit', '!=', 'SDU'))
                // B625 reverted (10/25/2026): gcu_staff is back to unit-wide
                // visibility on GCU appointments, not just their own caseload.
                ->orderByDesc('created_at')
                ->paginate($perPage)
        );
    }

    public function store(Request $request)
{
    $validated = $request->validate([
        'case_id'          => 'required|exists:cases,id',
        'referral_id'      => 'nullable|exists:referrals,id',
        'student_id'       => 'required|exists:students,id',
        'staff_user_id'    => 'nullable|exists:users,id',
        'unit'             => 'required|in:GCU,SDU,TMDU',
        'appointment_type' => 'required|string',
        'appointment_date' => [
            'required',
            'date',
            'after_or_equal:today',
            function ($attribute, $value, $fail) {
                if (\Carbon\Carbon::parse($value)->isWeekend()) {
                    $fail('Appointments can only be scheduled Monday through Friday.');
                }
            },
        ],
        'start_time'       => 'required|date_format:H:i',
        'end_time'         => 'required|date_format:H:i|after:start_time',
        'location'         => 'nullable|string',
        'notes'            => 'nullable|string|max:1000',
    ]);

        \App\Support\BlockedDates::assertOpen($validated['appointment_date']);

        $conflictFound = !empty($validated['staff_user_id'])
            ? Appointment::hasConflict($validated['staff_user_id'], $validated['appointment_date'], $validated['start_time'], $validated['end_time'])
            : Appointment::hasUnitConflict($validated['unit'], $validated['appointment_date'], $validated['start_time'], $validated['end_time']);

        if ($conflictFound) {
            return response()->json(['message' => 'Scheduling conflict: staff or unit is unavailable at this time.'], 422);
        }

        // StaffAvailability was previously only informational (shown on the
        // frontend via availability() below) - nothing actually stopped a
        // direct API call from booking a staff member outside their
        // declared hours. Only enforced when a specific staff member was
        // requested (not TBA/auto-assign) AND that staff has at least one
        // availability row for that day - staff who haven't configured
        // their availability yet aren't blocked outright, to avoid locking
        // out every booking for anyone who simply never filled this in.
        if (!empty($validated['staff_user_id'])) {
            $dayOfWeek = Carbon::parse($validated['appointment_date'])->format('l');
            $dayRows = StaffAvailability::where('user_id', $validated['staff_user_id'])
                ->where('day_of_week', $dayOfWeek)
                ->get();

            if ($dayRows->isNotEmpty()) {
                $fits = $dayRows->contains(fn ($row) =>
                    $row->is_available
                    && $validated['start_time'] >= $row->start_time
                    && $validated['end_time'] <= $row->end_time
                );

                if (!$fits) {
                    return response()->json(['message' => 'This staff member is not available at the selected date/time.'], 422);
                }
            }
        }

        // Fallback to the creating user if TBA/auto-assign was chosen (column is NOT NULL)
        $validated['staff_user_id'] = ($validated['staff_user_id'] ?? null) ?: $request->user()->id;

        // This endpoint is only ever used for a staff member directly
        // booking a date/time with the student already agreed face-to-face
        // (currently: Schedule Follow-up on the SIF) - there is no
        // self-scheduling step to wait on here, unlike the scheduling-link
        // flow in ReferralController::acknowledge()/PublicSchedulingController.
        // Without this, the appointment fell back to the appointments table's
        // request_status default of 'awaiting_student', which put it in the
        // student's "pending - pick your own time" queue with no scheduling
        // token to actually act on, and none of the confirmed-appointment
        // actions (Student Attended / No-Show / Request Reschedule / Cancel)
        // available anywhere.
        $appt = Appointment::create([
            ...$validated,
            'created_by_user_id' => $request->user()->id,
            'duration_minutes'   => $this->calcDuration($validated['start_time'], $validated['end_time']),
            'status'              => 'confirmed',
            'request_status'      => 'confirmed',
            'confirmation_sent'    => true,
            'confirmation_sent_at' => now(),
        ]);

        AuditLog::record('created', "Scheduled appointment {$appt->appointment_code}.", $appt);

        Notification::send($appt->student, new AppointmentConfirmedNotification($appt));
        $this->notifyReferrer($appt);

        return response()->json($appt->load(['student', 'staff', 'createdBy']), 201);
    }

    public function show(Appointment $appointment)
    {
        $this->authorizeUnit($appointment);
        return response()->json($appointment->load(['student', 'staff', 'case', 'createdBy', 'slotHistory']));
    }

    public function update(Request $request, Appointment $appointment)
    {
        $this->authorizeUnit($appointment);
        $validated = $request->validate([
            'location'         => 'nullable|string',
            'notes'            => 'nullable|string|max:1000',
            'appointment_type' => 'nullable|string',
        ]);

        // A follow-up session's own notes are gated on THAT follow-up's own
        // attendance. The general SIF-wide attendance gate has been removed
        // (per the updated referral process); this narrower, follow-up-only
        // lock is the one piece of it that's intentionally kept - GCU must
        // click "Student Attended" on this specific follow-up before adding
        // notes for it.
        if (array_key_exists('notes', $validated)
            && $appointment->appointment_type === 'follow_up_session'
            && !$appointment->checked_in) {
            abort(422, 'The student has not yet attended this follow-up session.');
        }

        // Follow-up notes are write-once: after they are saved they can no
        // longer be changed, and nothing on a closed SIF can be edited.
        if (array_key_exists('notes', $validated)
            && $appointment->appointment_type === 'follow_up_session') {
            if (trim((string) $appointment->notes) !== '' && $validated['notes'] !== $appointment->notes) {
                abort(422, 'Follow-up notes were already saved and can no longer be edited.');
            }
            $appointment->referral?->abortIfLocked();
        }

        $old = $appointment->toArray();
        $appointment->update($validated);
        AuditLog::record('updated', "Updated appointment {$appointment->appointment_code}.", $appointment, $old, $appointment->toArray());
        return response()->json($appointment->load('createdBy'));
    }

    public function confirm(Request $request, Appointment $appointment)
    {
        $this->authorizeUnit($appointment);
        $validated = $request->validate([
            'staff_user_id'       => 'nullable|exists:users,id',
            'required_documents'  => 'nullable|string|max:1000',
        ]);

        \App\Support\BlockedDates::assertOpen($appointment->appointment_date?->format('Y-m-d'));

        $staffToAssign = $validated['staff_user_id'] ?? $appointment->staff_user_id;
        $conflict = $staffToAssign ? Appointment::findConflict(
            $staffToAssign,
            $appointment->appointment_date->format('Y-m-d'),
            $appointment->start_time,
            $appointment->end_time,
            $this->rescheduleChainIds($appointment)
        ) : null;

        if ($conflict) {
            $who = $conflict->student ? " ({$conflict->student->last_name}, {$conflict->student->first_name})" : '';
            return response()->json([
                'message' => "This staff member already has a conflicting appointment at this time: {$conflict->appointment_code}{$who}, {$conflict->start_time}-{$conflict->end_time}, status {$conflict->status}.",
            ], 422);
        }

        // Same StaffAvailability gate as store() - this is the actual
        // "GCU determines if time is available" step for a student's
        // self-picked slot, so it needs the same real enforcement, not just
        // a conflict check. Only enforced when that staff has configured
        // availability for the day at all (see store() for why).
        if ($staffToAssign) {
            $dayOfWeek = Carbon::parse($appointment->appointment_date->format('Y-m-d'))->format('l');
            $dayRows = StaffAvailability::where('user_id', $staffToAssign)
                ->where('day_of_week', $dayOfWeek)
                ->get();

            if ($dayRows->isNotEmpty()) {
                $fits = $dayRows->contains(fn ($row) =>
                    $row->is_available
                    && $appointment->start_time >= $row->start_time
                    && $appointment->end_time <= $row->end_time
                );

                if (!$fits) {
                    return response()->json(['message' => 'This staff member is not available at the selected date/time. Ask the student to reschedule.'], 422);
                }
            }
        }

        // Class Attendance appointments always need the Admission Slip
        // documents mentioned to the student on confirmation - auto-fill
        // the reminder when staff didn't type their own required_documents
        // text, same pattern TestingRecordController::scheduleTesting() uses
        // for TESTING_REMINDER. Staff-provided text always wins.
        $requiredDocuments = $validated['required_documents']
            ?? ($appointment->referral?->referral_type === 'class_attendance' ? self::CLASS_ATTENDANCE_REMINDER : self::GENERAL_REMINDER);

        $appointment->update([
            'status'               => 'confirmed',
            'request_status'       => 'confirmed',
            'confirmation_sent'    => true,
            'confirmation_sent_at' => now(),
            ...(!empty($validated['staff_user_id']) ? ['staff_user_id' => $validated['staff_user_id']] : []),
            ...(!empty($requiredDocuments) ? ['required_documents' => $requiredDocuments] : []),
        ]);

        // B275: confirming the appointment moves the referral to 'scheduled'
        // - not straight to 'in_progress'. 'in_progress' is reserved for the
        // student actually attending (set in checkIn() below). Forward-only
        // via advanceStatusTo(), so this can't undo a later status.
        if ($appointment->case?->latestReferral) {
            $appointment->case->latestReferral->advanceStatusTo('scheduled');
        }

        AuditLog::record('confirmed', "Confirmed appointment {$appointment->appointment_code}.", $appointment);

        if ($appointment->student) {
            Notification::send($appointment->student, new AppointmentConfirmedNotification($appointment));
            $this->notifyReferrer($appointment);

            if (!empty($requiredDocuments)) {
                Notification::send($appointment->student, new DocumentsRequiredNotification($appointment));
            }
        }

        return response()->json($appointment);
    }

    // B234: a reschedule no longer mutates the original appointment in
    // place. It creates a separate, linked Appointment row (via
    // rescheduled_from_id) for the new slot, and marks the original row
    // with status 'rescheduled' so it stops appearing as an open/pending
    // appointment while still being kept around as a record. This lets the
    // whole chain of an appointment's reschedules be counted and traced.
    public function reschedule(Request $request, Appointment $appointment)
    {
        $this->authorizeUnit($appointment);
        $request->validate([
            'reschedule_reason' => ['required', 'string', 'max:1000', 'regex:/\p{L}/u'],
        ], ['reschedule_reason.regex' => 'Please explain the reason in words, not numbers only.']);

        $reason = trim($request->reschedule_reason);
        $old = $this->slotSnapshot($appointment);

        // B469: the SAME appointment is updated - no second record. The slot it gives up goes to the
        // history (never offered again) and the student is asked to pick a new one.
        $newToken = $this->reopenForStudent($appointment, $reason, 'staff', $request->user()->name, $appointment->reschedule_count);

        AuditLog::record('reschedule_requested',
            "Requested reschedule for appointment {$appointment->appointment_code} (same record updated, no new appointment). "
            . "Previous slot: {$old['date']} {$old['start']}-{$old['end']}. Reason: {$reason}. Rescheduled by {$request->user()->name}. Reschedule #{$appointment->times_rescheduled}. Student notified.",
            $appointment, $old, ['status' => 'pending', 'request_status' => 'awaiting_student']);

        return response()->json([
            'message'               => 'Reschedule request sent to student.',
            'appointment'           => $appointment->fresh()->load('slotHistory'),
            'previous_appointment'  => $old,
            'scheduling_link'       => rtrim((string) config('app.frontend_url'), '/') . "/schedule/{$newToken}",
        ]);
    }

    /** The current slot, as stored before a reschedule changes it. */
    private function slotSnapshot(Appointment $a): array
    {
        return [
            'date'  => $a->appointment_date?->format('Y-m-d'),
            'start' => substr((string) $a->start_time, 0, 5),
            'end'   => substr((string) $a->end_time, 0, 5),
            'staff_user_id' => $a->staff_user_id,
        ];
    }

    /**
     * Shared by staff- and student-initiated reschedules: stores the slot being given up,
     * reopens the SAME appointment for the student to pick a new schedule and notifies them.
     * Returns the new scheduling token.
     */
    private function reopenForStudent(Appointment $appointment, string $reason, string $byType, string $byName, int $newCount): string
    {
        $token = \Illuminate\Support\Str::random(48);
        DB::transaction(function () use ($appointment, $reason, $byType, $byName, $newCount, $token) {
            $old = $this->slotSnapshot($appointment);
            if ($old['date']) {
                \App\Models\AppointmentSlotHistory::create([
                    'appointment_id' => $appointment->id, 'slot_date' => $old['date'], 'start_time' => $old['start'], 'end_time' => $old['end'],
                    'staff_user_id' => $old['staff_user_id'], 'reason' => $reason, 'rescheduled_by_type' => $byType,
                    'rescheduled_by_name' => $byName, 'created_at' => now(),
                ]);
            }
            $appointment->update([
                'status'            => 'pending',
                'request_status'    => 'awaiting_student',
                'scheduling_token'  => $token,
                'token_expires_at'  => now()->addDays(7),
                'reschedule_reason' => $reason,
                'reschedule_count'  => $newCount,
                'times_rescheduled' => $appointment->times_rescheduled + 1,
                'checked_in'        => false,
                'checked_in_at'     => null,
            ]);
        });

        try {
            $appointment->student?->notify(new \App\Notifications\AppointmentRescheduledNotification($appointment->fresh(), $reason));
        } catch (\Throwable $e) {
            report($e);
        }
        return $token;
    }

    // The appointment itself plus every earlier appointment it was
    // rescheduled from. Those earlier rows are replaced by this one, so they
    // must never count as a conflicting booking against it.
    private function rescheduleChainIds(Appointment $appointment): array
    {
        $ids = [$appointment->id];
        $current = $appointment;
        while ($current->rescheduled_from_id && !in_array($current->rescheduled_from_id, $ids, true)) {
            $ids[] = $current->rescheduled_from_id;
            $current = Appointment::find($current->rescheduled_from_id);
            if (!$current) {
                break;
            }
        }
        return $ids;
    }

    // Student self-service reschedule - this is the only path that tracks
    // reschedule_count / RESCHEDULE_LIMIT; a staff-initiated reschedule()
    // above no longer touches that counter, since it isn't the student
    // repeatedly bailing on their own appointment. A student must give a
    // reason each time; the request that would push the count to the limit
    // is denied outright and escalated to a no-show/call-slip instead of
    // being granted a new scheduling link, since there's no staff member in
    // the loop to catch a runaway student.
    public function requestRescheduleByStudent(Request $request, Appointment $appointment)
    {
        if ($appointment->student_id !== $request->user('student')->id) {
            abort(403, 'This is not your appointment.');
        }

        if (!in_array($appointment->status, ['pending', 'confirmed', 'no_show'])) {
            return response()->json(['message' => 'This appointment can no longer be rescheduled.'], 422);
        }

        $request->validate(['reason' => ['required', 'string', 'max:1000', 'regex:/\p{L}/u']], ['reason.regex' => 'Please explain the reason in words, not numbers only.']);

        $newCount = $appointment->reschedule_count + 1;

        if ($newCount >= self::RESCHEDULE_LIMIT) {
            $appointment->update([
                'status'               => 'no_show',
                'reschedule_count'     => $newCount,
                'no_show_escalated'    => true,
                'no_show_escalated_at' => now(),
                'call_slip_stage'      => $appointment->call_slip_stage ?? 'pending',
            ]);

            $this->notifyDeanSecretaries($appointment, new NoShowEscalationNotification($appointment));
            AuditLog::record('reschedule_limit_reached', "Student exceeded the reschedule limit for appointment {$appointment->appointment_code}. Flagged for staff follow-up.", $appointment);

            return response()->json([
                'message'     => "You've reached the limit of " . self::RESCHEDULE_LIMIT . " reschedule requests for this appointment. This has been flagged for staff follow-up.",
                'appointment' => $appointment,
            ], 422);
        }

        $reason = trim($request->reason);
        $old = $this->slotSnapshot($appointment);
        $student = $request->user('student');
        $newToken = $this->reopenForStudent($appointment, $reason, 'student', trim($student->first_name . ' ' . $student->last_name), $newCount);

        AuditLog::record('reschedule_requested',
            "Student requested reschedule for appointment {$appointment->appointment_code} (same record updated, no new appointment). "
            . "Previous slot: {$old['date']} {$old['start']}-{$old['end']}. Reason: {$reason}. Reschedule #{$appointment->times_rescheduled}.",
            $appointment, $old, ['status' => 'pending', 'request_status' => 'awaiting_student']);
        $appointment->notifyOffice('reschedule_requested');

        return response()->json([
            'message'              => 'Reschedule requested.',
            'appointment'          => $appointment->fresh()->load('slotHistory'),
            'previous_appointment' => $old,
            'scheduling_link'      => rtrim((string) config('app.frontend_url'), '/') . "/schedule/{$newToken}",
        ]);
    }

    private function notifyDeanSecretaries(Appointment $appointment, $notification): void
    {
        $deanSecretaries = User::whereIn('role', ['dean', 'dean_secretary'])
            ->where('college', $appointment->student->college)
            ->where('is_active', true)
            ->get();

        foreach ($deanSecretaries as $secretary) {
            // The action itself (no-show, call slip) has already been saved
            // by the time this runs, so a mail/SMTP failure must not turn
            // the whole request into a 500.
            try {
                $secretary->notify($notification);
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    public function cancel(Request $request, Appointment $appointment)
{
    $request->validate(['cancellation_reason' => 'required|string|max:1000']);

    $appointment->update([
        'status'               => 'cancelled',
        'request_status'       => 'confirmed',
        'cancellation_reason'  => $request->cancellation_reason,
        'cancelled_at'         => now(),
        'cancelled_by_user_id' => $request->user()->id,
    ]);

        // The linked referral was moved to 'scheduled'/'in_progress' when this
        // appointment was booked/confirmed (see PublicSchedulingController::submit
        // and self::confirm). Cancelling the appointment has to reflect that on the
        // referral too, so the Student Profile shows "Cancelled" instead of a stale
        // "Scheduled" label (B193).
        if ($appointment->case?->latestReferral && in_array($appointment->case->latestReferral->status, ['scheduled', 'in_progress'])) {
            $appointment->case->latestReferral->advanceStatusTo('cancelled');
        }

        AuditLog::record('cancelled', "Cancelled appointment {$appointment->appointment_code}.", $appointment);
        return response()->json($appointment);
    }

    public function indexByStudent(Request $request)
{
    $student = $request->user('student');

    $appointments = $student->appointments()
        ->with(['staff', 'referral', 'case.latestReferral'])
        ->latest()
        ->get();

    return response()->json(['data' => $appointments]);
}

public function storeByStudent(Request $request)
{
    $student = $request->user('student');


    $validated = $request->validate([
        'concern'          => 'required|string|max:255',
        'notes'            => 'nullable|string|max:1000',
        'appointment_date' => [
            'required', 'date', 'after_or_equal:today',
            function ($attr, $value, $fail) {
                if (\Carbon\Carbon::parse($value)->isWeekend()) {
                    $fail('Appointments can only be scheduled Monday through Friday.');
                }
            },
        ],
        'start_time'       => 'required|date_format:H:i',
        'end_time'         => 'required|date_format:H:i|after:start_time',
    ]);

    \App\Support\BlockedDates::assertOpen($validated['appointment_date']);

    if (Appointment::hasUnitConflict(
        'GCU',
        $validated['appointment_date'],
        $validated['start_time'],
        $validated['end_time']
    )) {
        return response()->json(['message' => 'That time slot is already taken.'], 422);
    }

    $appointment = Appointment::create([
        'student_id'         => $student->id,
        'case_id'            => null,
        'staff_user_id'      => null,
        'created_by_user_id' => null,
        'unit'               => 'GCU',
        'appointment_type'   => 'initial_counseling',
        'appointment_date'   => $validated['appointment_date'],
        'start_time'         => $validated['start_time'],
        'end_time'           => $validated['end_time'],
        'duration_minutes'   => $this->calcDuration($validated['start_time'], $validated['end_time']),
        'notes'              => $validated['notes'] ?? null,
        'status'             => 'pending',
        'request_status'     => 'pending_confirmation',
    ]);

    AuditLog::record('created', "Student self-booked appointment {$appointment->appointment_code}.", $appointment);

    return response()->json($appointment->load('student'), 201);
}

public function checkConflictByStudent(Request $request)
{
    $validated = $request->validate([
        'appointment_date' => 'required|date',
        'start_time'       => 'required|date_format:H:i',
        'end_time'         => 'required|date_format:H:i',
    ]);

    $blocked = \App\Support\BlockedDates::message($validated['appointment_date']);
    if ($blocked) {
        return response()->json(['has_conflict' => true, 'blocked' => true, 'message' => $blocked]);
    }

    $hasConflict = Appointment::hasUnitConflict(
        'GCU',
        $validated['appointment_date'],
        $validated['start_time'],
        $validated['end_time']
    );

    return response()->json(['has_conflict' => $hasConflict]);
}

    public function checkIn(Request $request, Appointment $appointment)
    {
        $this->authorizeUnit($appointment);

        // Only a live appointment can be marked attended.
        if (in_array($appointment->status, ['cancelled', 'no_show', 'rescheduled'], true)) {
            abort(422, 'This appointment is ' . str_replace('_', ' ', $appointment->status) . ' and cannot be marked as attended.');
        }

        $appointment->update([
            'checked_in'            => true,
            'checked_in_at'         => now(),
            'checked_in_by_user_id' => $request->user()->id,
            'status'                => 'completed',
        ]);

        // B275: "Student Attended" is what actually moves the referral to
        // 'in_progress' - this is the point the SIF unlocks (see
        // Referral::canEditSif(), which also requires an attended
        // appointment). Forward-only via advanceStatusTo().
        // Move the referral this appointment actually belongs to - not just
        // the case's latest one, which is a different referral whenever the
        // student has more than one under the same case.
        $referral = $appointment->referral ?: $appointment->case?->latestReferral;
        if ($referral) {
            $referral->advanceStatusTo('in_progress');
        }

        AuditLog::record('checked_in', "Student checked in for appointment {$appointment->appointment_code}.", $appointment);
        return response()->json($appointment->fresh()->load('student', 'referral', 'case.latestReferral'));
    }

    // FR 2.6: Mark a no-show. Whether this escalates to the Dean's
    // Secretary (Call Slip) or just gets marked so staff can ask the
    // student to reschedule is GCU's own call, made explicitly per
    // no-show via the `action` param - not an automatic threshold. The
    // case's no_show_count is still tracked and returned so staff have
    // that history in front of them when deciding, but it no longer
    // drives the decision itself.
    public function escalateNoShow(Request $request, Appointment $appointment)
    {
        $this->authorizeUnit($appointment);
        $validated = $request->validate([
            'action' => 'required|in:call_slip,reschedule',
        ]);

        // A Call Slip can only ever be sent once per appointment.
        if ($appointment->no_show_escalated) {
            abort(422, 'A Call Slip was already sent for this appointment.');
        }

        $callSlip = $validated['action'] === 'call_slip';

        // Count the no-show only the first time it is marked.
        if ($appointment->case_id && $appointment->status !== 'no_show') {
            $appointment->case->increment('no_show_count');
        }

        $appointment->update([
            'status'               => 'no_show',
            'no_show_escalated'    => $callSlip,
            'no_show_escalated_at' => $callSlip ? now() : null,
            ...($callSlip && !$appointment->call_slip_stage ? ['call_slip_stage' => 'pending'] : []),
        ]);

        if ($callSlip) {
            $this->notifyDeanSecretaries($appointment, new NoShowEscalationNotification($appointment));
            AuditLog::record('no_show_escalated', "No-show for appointment {$appointment->appointment_code}; GCU issued a Call Slip, escalated to Dean's Secretary.", $appointment);
            $message = "Marked as no-show and escalated to Dean's Secretary via Call Slip.";
        } else {
            AuditLog::record('no_show_marked', "Marked no-show for appointment {$appointment->appointment_code}; GCU chose to ask the student to reschedule.", $appointment);
            $message = 'Marked as no-show. Ask the student to reschedule.';
        }

        return response()->json([
            'message'     => $message,
            'appointment' => $appointment->load('case'),
        ]);
    }

    // Call Slip for a referral that was acknowledged but whose student never
    // picked a schedule (request_status still 'awaiting_student'). Nothing was
    // missed yet, so unlike escalateNoShow this does NOT mark a no-show or
    // bump the case's no_show_count - it only asks the Dean's Secretary to
    // follow up with the student.
    public function sendCallSlipUnscheduled(Request $request, Appointment $appointment)
    {
        $this->authorizeUnit($appointment);

        if ($appointment->request_status !== 'awaiting_student' || $appointment->status !== 'pending') {
            abort(422, 'A Call Slip can only be sent while the student has not picked a schedule yet.');
        }
        if ($appointment->no_show_escalated) {
            abort(422, 'A Call Slip was already sent for this appointment.');
        }

        $appointment->update([
            'no_show_escalated'    => true,
            'no_show_escalated_at' => now(),
            'call_slip_stage'      => $appointment->call_slip_stage ?: 'pending',
        ]);

        $this->notifyDeanSecretaries($appointment->loadMissing('student'), new UnscheduledCallSlipNotification($appointment));
        AuditLog::record('call_slip_unscheduled', "GCU sent a Call Slip for appointment {$appointment->appointment_code}: student has not picked a schedule.", $appointment);

        return response()->json([
            'message'     => "Call Slip sent to the Dean's Secretary.",
            'appointment' => $appointment->load('case'),
        ]);
    }

    // GCU Head only: manually move an appointment to any status, e.g. back from
    // No-Show to Pending / Waiting for Student, when the original change was a
    // mistake or the situation changed. A reason is required and audit-logged.
    public function overrideStatus(Request $request, Appointment $appointment)
    {
        $this->authorizeUnit($appointment);

        $data = $request->validate([
            'target' => 'required|in:pending,awaiting_student,pending_confirmation,confirmed,completed,no_show,cancelled',
            'reason' => 'required|string|min:5|max:255',
        ]);

        // TMDU sets its own appointments face to face, so they have no
        // student-picking or confirming step: only Pending, Attended, No Show
        // or Cancelled apply to them.
        $isTmdu = $appointment->unit === 'TMDU';
        if ($isTmdu && !in_array($data['target'], ['pending', 'completed', 'no_show', 'cancelled'], true)) {
            abort(422, 'A TMDU appointment can only be set to Pending, Attended, No Show or Cancelled.');
        }
        if (!$isTmdu && $data['target'] === 'pending') {
            abort(422, 'Choose Waiting for Student or Awaiting Confirmation instead.');
        }

        $map = [
            'pending'              => ['pending',   'confirmed'],
            'awaiting_student'     => ['pending',   'awaiting_student'],
            'pending_confirmation' => ['pending',   'pending_confirmation'],
            'confirmed'            => ['confirmed', 'confirmed'],
            'completed'            => ['completed', 'completed'],
            'no_show'              => ['no_show',   'no_show'],
            'cancelled'            => ['cancelled', 'cancelled'],
        ];
        [$status, $requestStatus] = $map[$data['target']];
        $from = $appointment->status . '/' . $appointment->request_status;

        // Keep the case's no-show tally in step with the override.
        $case = $appointment->case;
        if ($case) {
            if ($appointment->status === 'no_show' && $status !== 'no_show' && $case->no_show_count > 0) {
                $case->decrement('no_show_count');
            } elseif ($appointment->status !== 'no_show' && $status === 'no_show') {
                $case->increment('no_show_count');
            }
        }

        $attrs = ['status' => $status, 'request_status' => $requestStatus];

        if ($status === 'cancelled') {
            $attrs += [
                'cancelled_at'          => now(),
                'cancelled_by_user_id'  => $request->user()->id,
                'cancellation_reason'   => $data['reason'],
            ];
        } else {
            $attrs += ['cancelled_at' => null, 'cancelled_by_user_id' => null, 'cancellation_reason' => null];
        }

        if ($status === 'completed') {
            $attrs += ['checked_in' => true, 'checked_in_at' => $appointment->checked_in_at ?? now(), 'checked_in_by_user_id' => $appointment->checked_in_by_user_id ?? $request->user()->id];
        } else {
            $attrs += ['checked_in' => false, 'checked_in_at' => null, 'checked_in_by_user_id' => null];
        }

        $appointment->update($attrs);

        $who = match ($request->user()->role) {
            'tmdu_head' => 'TMDU Head',
            'gcu_staff' => 'GCU Staff',
            default     => 'Admin',
        };
        AuditLog::record('status_overridden', "{$who} changed appointment {$appointment->appointment_code} from {$from} to {$status}/{$requestStatus}. Reason: {$data['reason']}", $appointment);

        return response()->json([
            'message'     => 'Appointment status updated.',
            'appointment' => $appointment->fresh()->load(['student', 'staff', 'case.latestReferral', 'referral', 'createdBy']),
        ]);
    }

    // GCU staff/admin, from the Override Status panel: directly set a date
    // and time for a student still on "Waiting for Student to Pick a
    // Schedule" (request_status = awaiting_student), instead of waiting on
    // them to use their scheduling link. Mirrors the checks the student's
    // own PublicSchedulingController::submit() applies (weekday, business
    // hours, blocked dates, a unit-level conflict check).
    public function scheduleForStudent(Request $request, Appointment $appointment)
    {
        $this->authorizeUnit($appointment);

        if ($appointment->request_status !== 'awaiting_student') {
            abort(422, 'This appointment is not waiting for a schedule to be picked.');
        }

        $data = $request->validate([
            'appointment_date' => 'required|date',
            'start_time'       => 'required|date_format:H:i',
            'end_time'         => 'required|date_format:H:i|after:start_time',
        ]);

        $dayOfWeek = Carbon::parse($data['appointment_date'])->dayOfWeekIso;
        if ($dayOfWeek > 5) {
            abort(422, 'Appointments can only be scheduled Monday to Friday.');
        }
        if ($data['start_time'] < '08:00' || $data['end_time'] > '16:00') {
            abort(422, 'Appointments can only be scheduled between 8:00 AM and 4:00 PM.');
        }
        if ($blocked = \App\Support\BlockedDates::message($data['appointment_date'])) {
            abort(422, $blocked);
        }

        $conflict = Appointment::where('unit', $appointment->unit)
            ->where('appointment_date', $data['appointment_date'])
            ->where('id', '!=', $appointment->id)
            ->whereNotIn('status', ['cancelled', 'rescheduled'])
            ->where('request_status', '!=', 'awaiting_student')
            ->where('start_time', '<', $data['end_time'])
            ->where('end_time', '>', $data['start_time'])
            ->exists();
        if ($conflict) {
            abort(422, 'This time slot conflicts with another appointment for this unit. Please pick another.');
        }

        $old = ['date' => $appointment->appointment_date?->format('Y-m-d'), 'start' => $appointment->start_time, 'end' => $appointment->end_time];

        $appointment->update([
            'appointment_date' => $data['appointment_date'],
            'start_time'       => $data['start_time'],
            'end_time'         => $data['end_time'],
            'request_status'   => 'pending_confirmation',
            'status'           => 'pending',
        ]);

        if ($appointment->case?->latestReferral) {
            $appointment->case->latestReferral->update(['status' => 'scheduled']);
        }

        AuditLog::record('scheduled_by_staff',
            "{$request->user()->name} scheduled appointment {$appointment->appointment_code} for {$data['appointment_date']} {$data['start_time']}-{$data['end_time']} on the student's behalf.",
            $appointment, $old, $data);

        // Same as a student's own self-scheduling: this still needs office
        // confirmation (status stays "pending"), so no "confirmed" notice is
        // sent here - the usual confirm() step notifies the student once
        // that happens.
        $appointment->notifyOffice('scheduled');

        return response()->json([
            'message'     => 'Appointment scheduled for the student.',
            'appointment' => $appointment->fresh()->load(['student', 'staff', 'case.latestReferral', 'referral', 'createdBy']),
        ]);
    }

    public function availability(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'date'    => 'required|date',
        ]);

        $dayOfWeek = Carbon::parse($request->date)->format('l');

        $availability = StaffAvailability::where('user_id', $request->user_id)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_available', true)
            ->first();

        $bookedSlots = Appointment::where('staff_user_id', $request->user_id)
            ->where('appointment_date', $request->date)
            ->whereNotIn('status', ['cancelled'])
            ->get(['start_time', 'end_time', 'appointment_type', 'status']);

        return response()->json([
            'availability' => $availability,
            'booked_slots' => $bookedSlots,
        ]);
    }

    public function checkConflict(Request $request)
    {
        $request->validate([
            'staff_user_id'    => 'nullable|exists:users,id',
            'unit'             => 'nullable|string',
            'appointment_date' => 'required|date',
            'start_time'       => 'required',
            'end_time'         => 'required',
        ]);

        if ($blocked = \App\Support\BlockedDates::message($request->appointment_date)) {
            return response()->json(['has_conflict' => true, 'blocked' => true, 'message' => $blocked]);
        }

        $conflict = !empty($request->staff_user_id)
            ? Appointment::hasConflict($request->staff_user_id, $request->appointment_date, $request->start_time, $request->end_time, $request->exclude_id)
            : (!empty($request->unit) ? Appointment::hasUnitConflict($request->unit, $request->appointment_date, $request->start_time, $request->end_time, $request->exclude_id) : false);

        return response()->json(['has_conflict' => $conflict]);
    }

    private function calcDuration(string $start, string $end): int
    {
        return (int) Carbon::createFromFormat('H:i', $start)
            ->diffInMinutes(Carbon::createFromFormat('H:i', $end));
    }

    /** The instructor / dean who referred the student also hears that an appointment was set (FR-10). Never throws. */
    private function notifyReferrer(Appointment $appointment): void
    {
        try {
            $referrer = $appointment->case?->latestReferral?->referredBy;
            if ($referrer && $referrer->is_active) {
                Notification::send($referrer, new AppointmentConfirmedNotification($appointment, true));
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }
}