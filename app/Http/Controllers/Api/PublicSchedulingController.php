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
            ->with(['student', 'case.referral'])
            ->firstOrFail();

        return response()->json([
            'appointment' => $appointment,
            'student'     => $appointment->student,
            'referral'    => $appointment->case?->referral,
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
            ->where('start_time', '<', $request->end_time)
            ->where('end_time', '>', $request->start_time)
            ->exists();

        return response()->json(['available' => !$conflict]);
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

        if ($appointment->case?->referral) {
            $appointment->case->referral->update(['status' => 'scheduled']);
        }

        AuditLog::record('scheduled', "Student self-scheduled appointment {$appointment->appointment_code}.", $appointment);

        return response()->json(['message' => 'Appointment request submitted. The office will confirm shortly.', 'appointment' => $appointment]);
    }
}