<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Appointment;
use App\Models\StaffAvailability;
use Illuminate\Http\Request;
use Carbon\Carbon;

class AppointmentController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        return response()->json(
            Appointment::with(['student', 'staff', 'case'])
                ->when($request->date,   fn($q) => $q->where('appointment_date', $request->date))
                ->when($request->unit,   fn($q) => $q->where('unit', $request->unit))
                ->when($request->status, fn($q) => $q->where('status', $request->status))
                ->when($user->isTMDUStaff(), fn($q) => $q->where('unit', 'TMDU'))
                ->when($user->isSDUHead(),   fn($q) => $q->where('unit', 'SDU'))
                ->orderBy('appointment_date')
                ->orderBy('start_time')
                ->paginate(20)
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'case_id'          => 'required|exists:cases,id',
            'student_id'       => 'required|exists:students,id',
            'staff_user_id'    => 'required|exists:users,id',
            'unit'             => 'required|in:GCU,SDU,TMDU',
            'appointment_type' => 'required|string',
            'appointment_date' => 'required|date|after_or_equal:today',
            'start_time'       => 'required|date_format:H:i',
            'end_time'         => 'required|date_format:H:i|after:start_time',
            'location'         => 'nullable|string',
            'notes'            => 'nullable|string',
        ]);

        if (Appointment::hasConflict(
            $validated['staff_user_id'],
            $validated['appointment_date'],
            $validated['start_time'],
            $validated['end_time']
        )) {
            return response()->json(['message' => 'Scheduling conflict: staff is unavailable at this time.'], 422);
        }

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
        $appointment->update([
            'status'               => 'confirmed',
            'confirmation_sent'    => true,
            'confirmation_sent_at' => now(),
        ]);

        AuditLog::record('confirmed', "Confirmed appointment {$appointment->appointment_code}.", $appointment);
        return response()->json($appointment);
    }

    public function reschedule(Request $request, Appointment $appointment)
    {
        $request->validate([
            'appointment_date'  => 'required|date|after_or_equal:today',
            'start_time'        => 'required|date_format:H:i',
            'end_time'          => 'required|date_format:H:i|after:start_time',
            'reschedule_reason' => 'required|string',
        ]);

        if (Appointment::hasConflict(
            $appointment->staff_user_id,
            $request->appointment_date,
            $request->start_time,
            $request->end_time,
            $appointment->id
        )) {
            return response()->json(['message' => 'Scheduling conflict detected.'], 422);
        }

        $new = $appointment->replicate();
        $new->appointment_date    = $request->appointment_date;
        $new->start_time          = $request->start_time;
        $new->end_time            = $request->end_time;
        $new->rescheduled_from_id = $appointment->id;
        $new->reschedule_reason   = $request->reschedule_reason;
        $new->status              = 'pending';
        $new->confirmation_sent   = false;
        $new->save();

        $appointment->update(['status' => 'rescheduled']);

        AuditLog::record('rescheduled', "Rescheduled appointment {$appointment->appointment_code} to {$new->appointment_code}.", $new);
        return response()->json($new);
    }

    public function cancel(Request $request, Appointment $appointment)
    {
        $request->validate(['cancellation_reason' => 'required|string']);

        $appointment->update([
            'status'              => 'cancelled',
            'cancellation_reason' => $request->cancellation_reason,
            'cancelled_at'        => now(),
            'cancelled_by_user_id'=> $request->user()->id,
        ]);

        AuditLog::record('cancelled', "Cancelled appointment {$appointment->appointment_code}.", $appointment);
        return response()->json($appointment);
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
            'staff_user_id'    => 'required|exists:users,id',
            'appointment_date' => 'required|date',
            'start_time'       => 'required',
            'end_time'         => 'required',
        ]);

        $conflict = Appointment::hasConflict(
            $request->staff_user_id,
            $request->appointment_date,
            $request->start_time,
            $request->end_time,
            $request->exclude_id
        );

        return response()->json(['has_conflict' => $conflict]);
    }

    private function calcDuration(string $start, string $end): int
    {
        return (int) Carbon::createFromFormat('H:i', $start)
            ->diffInMinutes(Carbon::createFromFormat('H:i', $end));
    }
}