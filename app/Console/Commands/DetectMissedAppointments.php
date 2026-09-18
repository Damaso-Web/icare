<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Appointment;
use App\Models\User;
use App\Notifications\NoShowEscalationNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class DetectMissedAppointments extends Command
{
    protected $signature = 'app:detect-missed-appointments';

    protected $description = 'Auto-flag pending/confirmed appointments as no-show once their scheduled time has passed with no check-in.';

    public function handle(): int
    {
        $overdue = Appointment::whereIn('status', ['pending', 'confirmed'])
            ->where('request_status', '!=', 'awaiting_student')
            ->where('checked_in', false)
            ->where(function ($q) {
                $q->where('appointment_date', '<', now()->toDateString())
                  ->orWhere(function ($q2) {
                      $q2->where('appointment_date', now()->toDateString())
                         ->where('end_time', '<', now()->format('H:i'));
                  });
            })
            ->with('student')
            ->get();

        foreach ($overdue as $appointment) {
            $appointment->update([
                'status'               => 'no_show',
                'no_show_escalated'    => true,
                'no_show_escalated_at' => now(),
                ...(!$appointment->call_slip_stage ? ['call_slip_stage' => 'pending'] : []),
            ]);

            $deanSecretaries = User::where('role', 'dean_secretary')
                ->where('college', $appointment->student->college)
                ->where('is_active', true)
                ->get();
            Notification::send($deanSecretaries, new NoShowEscalationNotification($appointment));

            AuditLog::record('no_show_escalated', "Auto-detected missed appointment {$appointment->appointment_code}.", $appointment);
            $this->info("Flagged {$appointment->appointment_code} as no-show.");
        }

        $this->info("Checked for missed appointments - {$overdue->count()} flagged.");
        return self::SUCCESS;
    }
}
