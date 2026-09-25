<?php

namespace App\Notifications;

use App\Models\TestingRecord;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

// Sent specifically to the GCU counselor who originally referred this case
// to TMDU (testingRecord.referred_by_user_id) - not a broad "GCU staff"
// blast - once the PAR is ready and attached to the student's referral.
class ParReadyNotification extends Notification
{
    use Queueable;

    public function __construct(public TestingRecord $testingRecord) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $student = $this->testingRecord->student;

        return [
            'type'               => 'par_ready',
            'testing_record_id'  => $this->testingRecord->id,
            'case_id'            => $this->testingRecord->case_id,
            'message'            => "The Psychological Assessment Report (PAR) for {$student?->first_name} {$student?->last_name} is ready and has been attached to their referral.",
        ];
    }
}