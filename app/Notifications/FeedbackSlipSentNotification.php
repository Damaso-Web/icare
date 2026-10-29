<?php

namespace App\Notifications;

use App\Models\Referral;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

// Sent to the STUDENT when GCU sends a Feedback Slip, so they know a copy
// is also ready to view in the student portal (Referral Details).
//
// Bug (10/29/2026): the comment here used to say "the slip goes to the
// referrer either way, this just tells the student one exists" - but
// ReferralController::sendFeedback() never actually notified the referrer
// at all, staff or student. $forReferrer now lets this same notification
// go to whoever referred the case too: a staff referrer (via
// referred_by_user_id) or a student referrer (via referred_by_student_id,
// covering self-referral and student-to-student referrals) - see
// sendFeedback() for who gets which.
class FeedbackSlipSentNotification extends Notification
{
    use Queueable, \App\Notifications\Concerns\SendsEmail;

    public function __construct(public Referral $referral, public bool $forReferrer = false) {}

    public function toArray(object $notifiable): array
    {
        $r = $this->referral;

        return [
            'type'        => 'feedback_slip_sent',
            'referral_id' => $r->id,
            'message'     => $this->forReferrer
                ? "A feedback slip for the referral you made ({$r->referral_code}) has been sent."
                : "A feedback slip for your referral ({$r->referral_code}) has been sent. You can view it under My Referrals.",
        ];
    }
}