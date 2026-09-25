<?php

namespace App\Notifications;

use App\Models\Appointment;
use App\Models\TestingRecord;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

// Sent to the student once TMDU sets their testing appointment directly
// (they don't self-schedule this one - see TestingRecordController::scheduleTesting).
// The bring-these-items reminder is also written onto the appointment's own
// required_documents field, so it shows on their Appointment Details page too.
class TestingScheduledNotification extends Notification
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
            'type'               => 'testing_scheduled',
            'testing_record_id'  => $this->testingRecord->id,
            'appointment_id'     => $this->appointment->id,
            'message'            => "Your psychological testing has been scheduled for {$date} at {$this->appointment->start_time}. Bring your OR and two sharpened pencils with eraser, and arrive at least 15 minutes early.",
        ];
    }
}