<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/** In-app notice about a case/referral change: status override or reassignment (sent to the student and the receiving staff member), by app and by email. */
class CaseUpdateNotification extends Notification
{
    use Queueable, \App\Notifications\Concerns\SendsEmail;

    public function __construct(public string $message, public ?int $referralId = null, public ?int $caseId = null, public ?string $referralCode = null) {}

    public function emailSubject(object $notifiable): string
    {
        return 'Update on your case or referral';
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'          => 'case_update',
            'referral_id'   => $this->referralId,
            'referral_code' => $this->referralCode,
            'case_id'       => $this->caseId,
            'message'       => $this->message,
        ];
    }
}