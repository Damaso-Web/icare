<?php

namespace App\Notifications;

use App\Models\Referral;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

// Sent to the unit (GCU/SDU/TMDU) that owns this referral when its referrer
// asks to cancel/void it after acknowledgement - see
// ReferralController::requestVoid().
class VoidRequestedNotification extends Notification
{
    use Queueable, \App\Notifications\Concerns\SendsEmail;

    public function __construct(public Referral $referral) {}

    public function toArray(object $notifiable): array
    {
        return [
            'type'           => 'void_requested',
            'referral_id'    => $this->referral->id,
            'referral_code'  => $this->referral->referral_code,
            'student_name'   => $this->referral->student->first_name . ' ' . $this->referral->student->last_name,
            'message'        => "{$this->referral->referrer_name} requested cancellation of referral {$this->referral->referral_code}. Please review.",
        ];
    }
}