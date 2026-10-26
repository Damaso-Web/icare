<?php

namespace App\Notifications;

use App\Models\TestingRecord;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

// Sent to the student when TMDU attaches their Psychological Assessment
// Report (PAR). Their own copy is on the My Testing page.
class ParResultNotification extends Notification
{
    use Queueable, \App\Notifications\Concerns\SendsEmail;

    public function __construct(public TestingRecord $testingRecord) {}

    public function toArray(object $notifiable): array
    {
        return [
            'type'              => 'par_released',
            'testing_record_id' => $this->testingRecord->id,
            'message'           => 'Your Psychological Assessment Report (PAR) results are ready. You can view and download your copy under My Testing in iCARE.',
        ];
    }
}