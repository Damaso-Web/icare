<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Appointment;
use App\Models\StaffAvailability;
use App\Models\User;
use App\Notifications\AppointmentConfirmedNotification;
use App\Notifications\NoShowEscalationNotification;
use App\Notifications\DocumentsRequiredNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Carbon\Carbon;

class AppointmentController extends Controller
{
    // A reschedule limit any higher stops functioning as a limit at all.
    private const RESCHEDULE_LIMIT = 3;
    public function index(Request $request)
    {
        $user = $request->user();
        $perPage = $request->input('per_page', 20);

        return response()->json(
            Appointment::with(['student', 'staff', 'case'])
                ->when($request->date,        fn($q) => $q->where('appointment_date', $request->date))
                ->when($request->unit,        fn($q) => $q->where('unit', $request->unit))
                ->when($request->status, function ($q) use ($request) {
                    if (str_contains($request->status, ',')) {
                        $q->whereIn('status', explode(',', $request->status));
                    } else {
                        $q->where('status', $request->status);
                    }
                })
                ->when($request->referral_id,      fn($q) => $q->where('referral_id', $request->referral_id))
                ->when($request->appointment_type, fn($q) => $q->where('appointment_type', $request->appointment_type))
                ->when($user->isTMDUStaff(), fn($q) => $q->where('unit', 'TMDU'))
                ->when($user->isSDUHead(),   fn($q) => $q->where('unit', 'SDU'))
                ->orderBy('appointment_date')
                ->orderBy('start_time')
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
        'notes'            => 'nullable|string',
    ]);

        $conflictFound = !empty($validated['staff_user_id'])
            ? Appointment::hasConflict($validated['staff_user_id'], $validated['appointment_date'], $validated['start_time'], $validated['end_time'])
            : Appointment::hasUnitConflict($validated['unit'], $validated['appointment_date'], $validated['start_time'], $validated['end_time']);

        if ($conflictFound) {
            return response()->json(['message' => 'Scheduling conflict: staff or unit is unavailable at this time.'], 422);
        }

        // Fallback to the creating user if TBA/auto-assign was chosen (column is NOT NULL)
        $validated['staff_user_id'] = $validated['staff_user_id'] ?: $request->user()->id;

        $appt = Appointment::create([
            ...$validated,
            'created_by_user_id' => $request->user()->id,
            'duration_minutes'   => $this->calcDuration($validated['start_time'], $validated['end_time']),
        ]);

        AuditLog::record('created', "Scheduled appointment {$appt->appointment_code}.", $appt);
        return response()->json($appt->load(['student', 'staff']), 201);
    }

    public function show(Appointment $appointment)
    {
        return response()->json($appointment->load(['student', 'staff', 'case', 'createdBy']));
    }

    public function update(Request $request, Appointment $appointment)
    {
        $old = $appointment->toArray();
        $appointment->update($request->only(['location', 'notes', 'appointment_type']));
        AuditLog::record('updated', "Updated appointment {$appointment->appointment_code}.", $appointment, $old, $appointment->toArray());
        return response()->json($appointment);
    }

    public function confirm(Request $request, Appointment $appointment)
    {
        $validated = $request->validate([
            'staff_user_id'       => 'nullable|exists:users,id',
            'required_documents'  => 'nullable|string',
        ]);

        $staffToAssign = $validated['staff_user_id'] ?? $appointment->staff_user_id;
        if ($staffToAssign && Appointment::hasConflict(
            $staffToAssign,
            $appointment->appointment_date->format('Y-m-d'),
            $appointment->start_time,
            $appointment->end_time,
            $appointment->id
        )) {
            return response()->json(['message' => 'This staff member already has a conflicting appointment at this time.'], 422);
        }

        $appointment->update([
            'status'               => 'confirmed',
            'request_status'       => 'confirmed',
            'confirmation_sent'    => true,
            'confirmation_sent_at' => now(),
            ...(!empty($validated['staff_user_id']) ? ['staff_user_id' => $validated['staff_user_id']] : []),
            ...(!empty($validated['required_documents']) ? ['required_documents' => $validated['required_documents']] : []),
        ]);

        if ($appointment->case?->latestReferral) {
            $appointment->case->latestReferral->update(['status' => 'in_progress']);
        }

        AuditLog::record('confirmed', "Confirmed appointment {$appointment->appointment_code}.", $appointment);

        if ($appointment->student) {
            Notification::send($appointment->student, new AppointmentConfirmedNotification($appointment));

            if (!empty($validated['required_documents'])) {
                Notification::send($appointment->student, new DocumentsRequiredNotification($appointment));
            }
        }

        return response()->json($appointment);
    }

    public function reschedule(Request $request, Appointment $appointment)
{
    $request->validate([
        'reschedule_reason' => 'required|string',
    ]);

    $newToken = \Illuminate\Support\Str::random(48);
    $newCount = $appointment->reschedule_count + 1;

    $appointment->update([
        'request_status'    => 'awaiting_student',
        'status'            => 'pending',
        'scheduling_token'  => $newToken,
        'token_expires_at'  => now()->addDays(7),
        'reschedule_reason' => $request->reschedule_reason,
        'reschedule_count'  => $newCount,
        ...($newCount >= self::RESCHEDULE_LIMIT && !$appointment->call_slip_stage ? ['call_slip_stage' => 'pending'] : []),
    ]);

    AuditLog::record('reschedule_requested', "Requested reschedule for appointment {$appointment->appointment_code}. Reason: {$request->reschedule_reason}", $appointment);

    if ($newCount >= self::RESCHEDULE_LIMIT) {
        $this->notifyDeanSecretaries($appointment, new NoShowEscalationNotification($appointment));
        AuditLog::record('call_slip_generated', "Appointment {$appointment->appointment_code} reached the reschedule limit; call slip generated.", $appointment);
    }

    return response()->json([
        'message'         => 'Reschedule request sent to student.',
        'appointment'     => $appointment,
        'scheduling_link' => url("/schedule/{$newToken}"),
    ]);
}

    // Student self-service reschedule - same counter/limit as the staff-side
    // reschedule() above. A student must give a reason each time; the request
    // that would push the count to the limit is denied outright and escalated
    // to a no-show/call-slip instead of being granted a new scheduling link,
    // since there's no staff member in the loop to catch a runaway student.
    public function requestRescheduleByStudent(Request $request, Appointment $appointment)
    {
        if ($appointment->student_id !== $request->user('student')->id) {
            abort(403, 'This is not your appointment.');
        }

        if (!in_array($appointment->status, ['pending', 'confirmed'])) {
            return response()->json(['message' => 'This appointment can no longer be rescheduled.'], 422);
        }

        $request->validate(['reason' => 'required|string']);

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

        $newToken = \Illuminate\Support\Str::random(48);

        $appointment->update([
            'request_status'    => 'awaiting_student',
            'status'            => 'pending',
            'scheduling_token'  => $newToken,
            'token_expires_at'  => now()->addDays(7),
            'reschedule_reason' => $request->reason,
            'reschedule_count'  => $newCount,
        ]);

        AuditLog::record('reschedule_requested', "Student requested reschedule for appointment {$appointment->appointment_code}. Reason: {$request->reason}", $appointment);

        return response()->json([
            'message'         => 'Reschedule requested.',
            'appointment'     => $appointment,
            'scheduling_link' => url("/schedule/{$newToken}"),
        ]);
    }

    private function notifyDeanSecretaries(Appointment $appointment, $notification): void
    {
        $deanSecretaries = User::where('role', 'dean_secretary')
            ->where('college', $appointment->student->college)
            ->where('is_active', true)
            ->get();

        foreach ($deanSecretaries as $secretary) {
            $secretary->notify($notification);
        }
    }

    public function cancel(Request $request, Appointment $appointment)
{
    $request->validate(['cancellation_reason' => 'required|string']);

    $appointment->update([
        'status'               => 'cancelled',
        'request_status'       => 'confirmed',
        'cancellation_reason'  => $request->cancellation_reason,
        'cancelled_at'         => now(),
        'cancelled_by_user_id' => $request->user()->id,
    ]);

        AuditLog::record('cancelled', "Cancelled appointment {$appointment->appointment_code}.", $appointment);
        return response()->json($appointment);
    }

    public function cancelByStudent(Request $request, Appointment $appointment)
    {
        if ($appointment->student_id !== $request->user('student')->id) {
            abort(403, 'This is not your appointment.');
        }

        if (!in_array($appointment->status, ['pending', 'confirmed'])) {
            return response()->json(['message' => 'This appointment can no longer be cancelled.'], 422);
        }

        $request->validate(['cancellation_reason' => 'required|string']);

        $appointment->update([
            'status'              => 'cancelled',
            'cancellation_reason' => $request->cancellation_reason,
            'cancelled_at'        => now(),
        ]);

        AuditLog::record('cancelled', "Student cancelled appointment {$appointment->appointment_code}. Reason: {$request->cancellation_reason}", $appointment);

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
        $appointment->update([
            'checked_in'            => true,
            'checked_in_at'         => now(),
            'checked_in_by_user_id' => $request->user()->id,
            'status'                => 'completed',
        ]);

        AuditLog::record('checked_in', "Student checked in for appointment {$appointment->appointment_code}.", $appointment);
        return response()->json($appointment);
    }

    // FR 2.6: Escalate No-Show to Dean's Secretary
    public function escalateNoShow(Request $request, Appointment $appointment)
    {
        $appointment->update([
            'status'               => 'no_show',
            'no_show_escalated'    => true,
            'no_show_escalated_at' => now(),
            ...(!$appointment->call_slip_stage ? ['call_slip_stage' => 'pending'] : []),
        ]);

        $this->notifyDeanSecretaries($appointment, new NoShowEscalationNotification($appointment));

        AuditLog::record('no_show_escalated', "No-show escalated for appointment {$appointment->appointment_code} to Dean's Secretary.", $appointment);

        return response()->json([
            'message'    => 'No-show escalated to Dean\'s Secretary.',
            'appointment'=> $appointment,
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
}