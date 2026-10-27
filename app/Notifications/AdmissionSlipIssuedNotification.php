<?php

namespace App\Notifications;

use App\Models\Referral;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

// Sent to the STUDENT when GCU issues or updates the Admission Slip for a
// Class Attendance referral, so they know a copy is ready to view in the
// student portal (Referral Details).
class AdmissionSlipIssuedNotification extends Notification
{
    use Queueable, \App\Notifications\Concerns\SendsEmail;

    public function __construct(public Referral $referral) {}

    public function toArray(object $notifiable): array
    {
        $r = $this->referral;

        return [
            'type'        => 'admission_slip_issued',
            'referral_id' => $r->id,
            'message'     => "An admission slip for your referral ({$r->referral_code}) has been issued. You can view it under My Referrals.",
        ];
    }
}