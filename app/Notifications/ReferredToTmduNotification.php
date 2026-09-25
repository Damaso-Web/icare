<?php

namespace App\Notifications;

use App\Models\CaseFile;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

// Sent to the STUDENT when GCU hands their case off to TMDU for psychological
// testing (TestingReferralNotification, above in the same referral flow, is
// the equivalent one sent to TMDU staff).
class ReferredToTmduNotification extends Notification
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
            'type'        => 'referred_to_tmdu',
            'case_id'     => $this->case->id,
            'case_number' => $this->case->case_number,
            'message'     => 'Your counselor has referred you to the Testing and Measurement Development Unit (TMDU) for psychological testing. Please check your appointments for next steps.',
        ];
    }
}