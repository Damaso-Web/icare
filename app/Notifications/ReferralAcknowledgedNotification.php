<?php

namespace App\Notifications;

use App\Models\Referral;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ReferralAcknowledgedNotification extends Notification
{
    use Queueable;

    public function __construct(public Referral $referral) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'           => 'referral_acknowledged',
            'referral_id'    => $this->referral->id,
            'referral_code'  => $this->referral->referral_code,
            'student_name'   => $this->referral->student->first_name . ' ' . $this->referral->student->last_name,
            'message'        => "Referral {$this->referral->referral_code} has been acknowledged by GCU.",
        ];
    }
}
