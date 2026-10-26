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
    use Queueable, \App\Notifications\Concerns\SendsEmail;

    public function __construct(public TestingRecord $testingRecord, public Appointment $appointment, public bool $rescheduled = false) {}


    public function toArray(object $notifiable): array
    {
        $date = $this->appointment->appointment_date instanceof \DateTimeInterface
            ? $this->appointment->appointment_date->format('F j, Y')
            : $this->appointment->appointment_date;

        $time = $this->appointment->start_time
            ? \Carbon\Carbon::parse($this->appointment->start_time)->format('g:i A')
            : '';
        $verb = $this->rescheduled ? 'rescheduled to' : 'scheduled for';

        return [
            'type'               => 'testing_scheduled',
            'testing_record_id'  => $this->testingRecord->id,
            'appointment_id'     => $this->appointment->id,
            'message'            => "Your psychological testing has been {$verb} {$date} at {$time}. Bring your OR and two sharpened pencils with eraser, and arrive at least 15 minutes early.",
        ];
    }
}