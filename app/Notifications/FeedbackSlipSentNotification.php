<?php

namespace App\Notifications;

use App\Models\Referral;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

// Sent to the STUDENT when GCU sends a Feedback Slip to the referrer, so
// they know a copy is also ready to view in the student portal (Referral
// Details) - the slip goes to the referrer either way, this just tells the
// student one exists.
class FeedbackSlipSentNotification extends Notification
{
    use Queueable, \App\Notifications\Concerns\SendsEmail;

    public function __construct(public Referral $referral) {}

    public function toArray(object $notifiable): array
    {
        $r = $this->referral;

        return [
            'type'        => 'feedback_slip_sent',
            'referral_id' => $r->id,
            'message'     => "A feedback slip for your referral ({$r->referral_code}) has been sent. You can view it under My Referrals.",
        ];
    }
}