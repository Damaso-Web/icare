<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class CallSlipController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $appointments = Appointment::where('no_show_escalated', true)
            ->whereHas('student', function ($q) use ($user) {
                $q->where('college', $user->college);
            })
            ->with(['student', 'case'])
            ->orderByDesc('no_show_escalated_at')
            ->paginate(20);

        return response()->json($appointments);
    }

    public function markContacted(Request $request, Appointment $appointment)
    {
        $request->validate(['notes' => 'nullable|string']);

        $appointment->update([
            'call_slip_stage' => 'contacted',
            'call_slip_notes' => $request->notes,
        ]);

        AuditLog::record('call_slip_contacted', "Dean's Secretary contacted student for appointment {$appointment->appointment_code}.", $appointment);

        return response()->json($appointment);
    }

    public function requestReschedule(Request $request, Appointment $appointment)
    {
        $newToken = \Illuminate\Support\Str::random(48);

        $appointment->update([
            'request_status'    => 'awaiting_student',
            'status'            => 'pending',
            'scheduling_token'  => $newToken,
            'token_expires_at'  => now()->addDays(7),
            'call_slip_stage'   => 'rescheduled',
        ]);

        AuditLog::record('call_slip_reschedule', "Dean's Secretary requested reschedule for appointment {$appointment->appointment_code}.", $appointment);

        return response()->json(['message' => 'Reschedule request sent to student.', 'appointment' => $appointment]);
    }

    public function escalateToDeptChair(Request $request, Appointment $appointment)
    {
        $request->validate(['notes' => 'nullable|string']);

        $appointment->update([
            'call_slip_stage' => 'dept_chair',
            'call_slip_notes' => $request->notes,
        ]);

        AuditLog::record('call_slip_escalated', "Escalated to Department Chair for appointment {$appointment->appointment_code}.", $appointment);

        return response()->json($appointment);
    }
}