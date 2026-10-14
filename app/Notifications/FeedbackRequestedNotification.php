<?php

namespace App\Notifications;

use App\Models\Referral;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

// The person who referred a student asks GCU for the Feedback Slip of a
// completed referral.
class FeedbackRequestedNotification extends Notification
{
    use Queueable;

    public function __construct(public Referral $referral, public string $requestedBy)
    {
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        $student = $this->referral->student;
        $name = $student ? trim($student->first_name . ' ' . $student->last_name) : 'a student';

        return [
            'type'          => 'feedback_requested',
            'referral_id'   => $this->referral->id,
            'referral_code' => $this->referral->referral_code,
            'message'       => "{$this->requestedBy} is asking for the Feedback Slip of referral {$this->referral->referral_code} ({$name}).",
            'url'           => "/referrals/{$this->referral->id}?ctx=cases",
        ];
    }
}
