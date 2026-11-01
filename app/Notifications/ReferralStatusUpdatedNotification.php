<?php

namespace App\Notifications;

use App\Models\Referral;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ReferralStatusUpdatedNotification extends Notification
{
    use Queueable, \App\Notifications\Concerns\SendsEmail;

    public function __construct(public Referral $referral, public string $newStatus) {}


    public function toArray(object $notifiable): array
    {
        return [
            'type'          => 'referral_status_updated',
            'referral_id'   => $this->referral->id,
            'referral_code' => $this->referral->referral_code,
            'status'        => $this->newStatus,
                        'message'       => 'Your referral for ' . $this->referral->student->first_name . ' ' . $this->referral->student->last_name . ' has been ' . ucwords(str_replace('_', ' ', $this->newStatus)) . '.',
        ];
    }
}