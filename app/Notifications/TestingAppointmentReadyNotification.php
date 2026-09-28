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
            // No scheduling link - picking up the Assessment of Fees form is
            // a walk-in, not an appointment. The student pays, then uploads
            // their OR (requestTestingByStudent()), and TMDU sets the actual
            // testing date after that (scheduleTesting()).
            'message'            => 'Your psychological testing referral has been acknowledged. Please proceed to TMDU to pick up your Assessment of Fees form.',
        ];
    }
}