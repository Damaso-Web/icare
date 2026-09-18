<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Models\User;
use App\Notifications\NoShowEscalationNotification;
use Illuminate\Console\Command;

class DetectMissedAppointments extends Command
{
    protected $signature = 'appointments:detect-no-show';
    protected $description = 'Automatically mark confirmed appointments as no-show once their end time has passed without check-in';

    public function handle()
    {
        $now = now();

        $overdue = Appointment::where('status', 'confirmed')
            ->where('checked_in', false)
            ->whereRaw("CONCAT(appointment_date, ' ', end_time) < ?", [$now->format('Y-m-d H:i:s')])
            ->with('student')
            ->get();

        $flagged = 0;

        foreach ($overdue as $appointment) {
            $appointment->update([
                'status'               => 'no_show',
                'no_show_escalated'    => true,
                'no_show_escalated_at' => now(),
            ]);

            $deanSecretaries = User::where('role', 'dean_secretary')
                ->where('college', $appointment->student->college)
                ->where('is_active', true)
                ->get();

            foreach ($deanSecretaries as $secretary) {
                $secretary->notify(new NoShowEscalationNotification($appointment));
            }

            $flagged++;
        }

        $this->info("Flagged {$flagged} missed appointment(s) as no-show.");
    }
}