<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class PublicSchedulingController extends Controller
{
    public function show($token)
    {
        $appointment = Appointment::where('scheduling_token', $token)
            ->where('token_expires_at', '>=', now())
            ->with(['student', 'case.latestReferral'])
            ->firstOrFail();

        return response()->json([
            'appointment' => $appointment,
            'student'     => $appointment->student,
            'referral'    => $appointment->case?->latestReferral,
        ]);
    }

    public function checkAvailability(Request $request, $token)
    {
        $appointment = Appointment::where('scheduling_token', $token)->firstOrFail();

        $request->validate([
            'appointment_date' => 'required|date',
            'start_time'       => 'required',
            'end_time'         => 'required',
        ]);

        if ($request->end_time <= $request->start_time) {
            return response()->json(['available' => false, 'message' => 'End time must be later than start time.']);
        }

        $date = new \DateTime($request->appointment_date);
        $dayOfWeek = (int) $date->format('N');

        if ($dayOfWeek > 5) {
            return response()->json(['available' => false, 'message' => 'Appointments can only be scheduled Monday to Friday.']);
        }

        if ($request->start_time < '08:00' || $request->end_time > '16:00') {
            return response()->json(['available' => false, 'message' => 'Appointments can only be scheduled between 8:00 AM and 4:00 PM.']);
        }

        $conflict = Appointment::where('unit', $appointment->unit)
            ->where('appointment_date', $request->appointment_date)
            ->where('id', '!=', $appointment->id)
            ->whereNotIn('status', ['cancelled'])
            ->where('request_status', '!=', 'awaiting_student')
            ->where('start_time', '<', $request->end_time)
            ->where('end_time', '>', $request->start_time)
            ->exists();

        return response()->json(['available' => !$conflict]);
    }

    public function monthAvailability(Request $request, $token)
    {
        $appointment = Appointment::where('scheduling_token', $token)->firstOrFail();

        $request->validate([
            'month' => 'required|date_format:Y-m',
        ]);

        $start = \Carbon\Carbon::createFromFormat('Y-m-d', $request->month . '-01')->startOfMonth();
        $end   = $start->copy()->endOfMonth();

        $booked = Appointment::where('unit', $appointment->unit)
            ->where('id', '!=', $appointment->id)
            ->whereBetween('appointment_date', [$start->toDateString(), $end->toDateString()])
            ->whereNotIn('status', ['cancelled'])
            ->where('request_status', '!=', 'awaiting_student')
            ->get(['appointment_date', 'start_time', 'end_time']);

        // 8:00-16:00 is the whole bookable window for a unit in one day -
        // once booked minutes reach that, there's no room left to fit
        // another slot no matter how it's arranged.
        $dailyCapacityMinutes = 8 * 60;

        $bookedMinutesByDate = [];
        foreach ($booked as $a) {
            $date = $a->appointment_date instanceof \DateTimeInterface
                ? $a->appointment_date->format('Y-m-d')
                : substr($a->appointment_date, 0, 10);
            $minutes = (strtotime($a->end_time) - strtotime($a->start_time)) / 60;
            $bookedMinutesByDate[$date] = ($bookedMinutesByDate[$date] ?? 0) + max(0, $minutes);
        }

        $days = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $dateStr = $d->toDateString();
            $isWeekend = $d->dayOfWeekIso > 5;
            $isPast = $d->lt(now()->startOfDay());
            $bookedMinutes = $bookedMinutesByDate[$dateStr] ?? 0;

            $status = 'available';
            if ($isWeekend) {
                $status = 'closed';
            } elseif ($isPast) {
                $status = 'past';
            } elseif ($bookedMinutes >= $dailyCapacityMinutes) {
                $status = 'full';
            }

            $days[] = ['date' => $dateStr, 'status' => $status];
        }

        return response()->json(['days' => $days]);
    }

    public function submit(Request $request, $token)
    {
        $appointment = Appointment::where('scheduling_token', $token)
            ->where('token_expires_at', '>=', now())
            ->firstOrFail();

        $request->validate([
            'appointment_date' => 'required|date',
            'start_time'       => 'required',
            'end_time'         => 'required',
        ]);

        if ($request->end_time <= $request->start_time) {
            return response()->json(['message' => 'End time must be later than start time.'], 422);
        }

        $date = new \DateTime($request->appointment_date);
        $dayOfWeek = (int) $date->format('N');

        if ($dayOfWeek > 5) {
            return response()->json(['message' => 'Appointments can only be scheduled Monday to Friday.'], 422);
        }

        if ($request->start_time < '08:00' || $request->end_time > '16:00') {
            return response()->json(['message' => 'Appointments can only be scheduled between 8:00 AM and 4:00 PM.'], 422);
        }

        $conflict = Appointment::where('unit', $appointment->unit)
            ->where('appointment_date', $request->appointment_date)
            ->where('id', '!=', $appointment->id)
            ->whereNotIn('status', ['cancelled'])
            ->where('request_status', '!=', 'awaiting_student')
            ->where('start_time', '<', $request->end_time)
            ->where('end_time', '>', $request->start_time)
            ->exists();

        if ($conflict) {
            return response()->json(['message' => 'This time slot is no longer available. Please pick another.'], 422);
        }

        $appointment->update([
            'appointment_date' => $request->appointment_date,
            'start_time'       => $request->start_time,
            'end_time'         => $request->end_time,
            'request_status'   => 'pending_confirmation',
            'status'           => 'pending',
        ]);

        if ($appointment->case?->latestReferral) {
            $appointment->case->latestReferral->update(['status' => 'scheduled']);
        }

        AuditLog::record('scheduled', "Student self-scheduled appointment {$appointment->appointment_code}.", $appointment);

        return response()->json(['message' => 'Appointment request submitted. The office will confirm shortly.', 'appointment' => $appointment]);
    }
}