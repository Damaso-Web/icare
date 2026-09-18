<?php

namespace App\Notifications;

use App\Models\TestingRecord;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TestingAppointmentReadyNotification extends Notification
{
    use Queueable;

    public function __construct(public TestingRecord $testingRecord) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'               => 'testing_appointment_ready',
            'testing_record_id'  => $this->testingRecord->id,
            'message'            => 'Your psychological testing referral has been acknowledged. Please set your preferred appointment schedule.',
        ];
    }
}
