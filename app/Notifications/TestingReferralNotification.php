<?php

namespace App\Notifications;

use App\Models\CaseFile;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TestingReferralNotification extends Notification
{
    use Queueable;

    public function __construct(public CaseFile $case) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'         => 'testing_referral',
            'case_id'      => $this->case->id,
            'case_number'  => $this->case->case_number,
            'student_name' => $this->case->student->first_name . ' ' . $this->case->student->last_name,
            'message'      => "Case {$this->case->case_number} ({$this->case->student->first_name} {$this->case->student->last_name}) was referred to TMDU for psychological testing. Please acknowledge.",
        ];
    }
}
