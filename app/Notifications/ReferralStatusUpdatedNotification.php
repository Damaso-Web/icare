<?php

namespace App\Notifications;

use App\Models\Referral;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ReferralStatusUpdatedNotification extends Notification
{
    use Queueable;

    public function __construct(public Referral $referral, public string $newStatus) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'          => 'referral_status_updated',
            'referral_id'   => $this->referral->id,
            'referral_code' => $this->referral->referral_code,
            'status'        => $this->newStatus,
            'message'       => "Referral {$this->referral->referral_code} status changed to " . ucwords(str_replace('_', ' ', $this->newStatus)) . '.',
        ];
    }
}
