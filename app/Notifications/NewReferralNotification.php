<?php

namespace App\Notifications;

use App\Models\Referral;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewReferralNotification extends Notification
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
            'type'           => 'new_referral',
            'referral_id'    => $this->referral->id,
            'referral_code'  => $this->referral->referral_code,
            'referral_type'  => $this->referral->referral_type,
            'urgency_level'  => $this->referral->urgency_level,
            'student_name'   => $this->referral->student->first_name . ' ' . $this->referral->student->last_name,
            'message'        => "New referral {$this->referral->referral_code} submitted for " . $this->referral->student->first_name . ' ' . $this->referral->student->last_name . '.',
        ];
    }
}
