<?php

namespace App\Notifications;

use App\Models\Appointment;
use App\Models\TestingRecord;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

// Sent to the student once TMDU sets the appointment for them to receive
// their Psychological Assessment Report (PAR), after the exam is done.
class ParScheduledNotification extends Notification
{
    use Queueable;

    public function __construct(public TestingRecord $testingRecord, public Appointment $appointment) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $date = $this->appointment->appointment_date instanceof \DateTimeInterface
            ? $this->appointment->appointment_date->format('F j, Y')
            : $this->appointment->appointment_date;

        return [
            'type'              => 'par_scheduled',
            'testing_record_id' => $this->testingRecord->id,
            'appointment_id'    => $this->appointment->id,
            'message'           => "Your Psychological Assessment Report (PAR) will be ready for release on {$date} at {$this->appointment->start_time}.",
        ];
    }
}