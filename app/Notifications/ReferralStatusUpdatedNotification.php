<?php

namespace App\Notifications;

use App\Models\Referral;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class ReferralStatusUpdatedNotification extends Notification
{
    use Queueable;

    public function __construct(public Referral $referral, public string $oldStatus) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    protected function statusLabel(string $status): string
    {
        return str_replace('_', ' ', ucwords($status, '_'));
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Referral Status Update: ' . $this->referral->referral_code)
            ->greeting('Dear ' . $notifiable->name . ',')
            ->line('The status of a referral you submitted has been updated.')
            ->line('**Referral Code:** ' . $this->referral->referral_code)
            ->line('**Student:** ' . $this->referral->student->first_name . ' ' . $this->referral->student->last_name)
            ->line('**Previous Status:** ' . $this->statusLabel($this->oldStatus))
            ->line('**New Status:** ' . $this->statusLabel($this->referral->status))
            ->salutation('iCARE — BSU Office of Student Services');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'          => 'referral_status_updated',
            'referral_id'   => $this->referral->id,
            'referral_code' => $this->referral->referral_code,
            'old_status'    => $this->oldStatus,
            'new_status'    => $this->referral->status,
            'message'       => "Referral {$this->referral->referral_code} status changed to {$this->statusLabel($this->referral->status)}.",
        ];
    }
}