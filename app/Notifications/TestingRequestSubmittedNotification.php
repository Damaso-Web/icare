<?php

namespace App\Notifications;

use App\Models\TestingRecord;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

// Sent to TMDU staff when a student uploads their OR and requests their
// testing schedule - this is the signal that TMDU can now set the actual
// testing appointment (TestingRecordController::scheduleTesting).
class TestingRequestSubmittedNotification extends Notification
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
            'type'              => 'testing_request_submitted',
            'testing_record_id' => $this->testingRecord->id,
            'message'           => "{$student?->first_name} {$student?->last_name} submitted their OR and is requesting their psychological testing schedule.",
        ];
    }
}