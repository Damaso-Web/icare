<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\CallSlipEscalatedNotification;
use Illuminate\Http\Request;

class CallSlipController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // 3 mirrors AppointmentController::RESCHEDULE_LIMIT
        $appointments = Appointment::where(function ($q) {
                $q->where('no_show_escalated', true)
                  ->orWhere('reschedule_count', '>=', 3);
            })
            // A Department Chair only ever follows up on what the Dean's
            // Secretary has already escalated to them - the Dean's
            // Secretary's own unworked/contacted call slips aren't theirs
            // to see.
            ->when($user->isDeptChair(), function ($q) {
                $q->where('call_slip_stage', 'dept_chair');
            })
            ->whereHas('student', function ($q) use ($user) {
                $q->whereIn('college', \App\Support\CollegeName::equivalents($user->college));

                // A Department Chair only follows up on students of their own
                // department, not every student in the college.
                if ($user->isDeptChair()) {
                    \App\Support\DepartmentScope::whereProgramMatches($q, $user->department);
                }
            })
            ->with(['student', 'case'])
            ->orderByDesc('no_show_escalated_at')
            ->paginate(20);

        return response()->json($appointments);
    }

    public function markContacted(Request $request, Appointment $appointment)
    {
        $request->validate(['notes' => 'nullable|string']);

        // Bug: nothing stopped "Mark Contacted" from being clicked again
        // after a student was already marked contacted (or after the slip
        // moved further along - rescheduled/escalated) - each click just
        // overwrote the notes and re-logged the same action.
        if (in_array($appointment->call_slip_stage, ['contacted', 'rescheduled', 'dept_chair'], true)) {
            return response()->json([
                'message' => 'This student has already been marked contacted.',
                'errors'  => ['call_slip_stage' => ['This student has already been marked contacted.']],
            ], 422);
        }

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

        if ($appointment->call_slip_stage === 'dept_chair') {
            return response()->json([
                'message' => 'This student has already been escalated to the Department Chair.',
                'errors'  => ['call_slip_stage' => ['This student has already been escalated to the Department Chair.']],
            ], 422);
        }

        // Don't mark it escalated if no Department Chair would ever receive it.
        if ($this->deptChairsFor($appointment->loadMissing('student'))->isEmpty()) {
            return response()->json([
                'message' => "There is no active Department Chair for this student's department yet.",
                'errors'  => ['call_slip_stage' => ["There is no active Department Chair for this student's department yet."]],
            ], 422);
        }

        $appointment->update([
            'call_slip_stage' => 'dept_chair',
            'call_slip_notes' => $request->notes,
        ]);

        AuditLog::record('call_slip_escalated', "Escalated to Department Chair for appointment {$appointment->appointment_code}.", $appointment);

        // Bug: escalating only ever updated the record - no Department
        // Chair was actually told about it until now.
        $this->notifyDeptChairs($appointment->loadMissing('student'));

        return response()->json($appointment);
    }

    // Active Department Chairs of the student's own college AND department.
    private function deptChairsFor(Appointment $appointment)
    {
        $student = $appointment->student;

        return User::where('role', 'dept_chair')
            ->whereIn('college', \App\Support\CollegeName::equivalents($student->college))
            ->where('is_active', true)
            ->get()
            ->filter(fn ($chair) => \App\Support\DepartmentScope::matches($student->program, $chair->department))
            ->values();
    }

    private function notifyDeptChairs(Appointment $appointment): void
    {
        foreach ($this->deptChairsFor($appointment) as $chair) {
            // The escalation itself has already been saved by this point,
            // so a mail/SMTP failure must not turn the whole request into a
            // 500 - same guard as AppointmentController::notifyDeanSecretaries().
            try {
                $chair->notify(new CallSlipEscalatedNotification($appointment));
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }
}