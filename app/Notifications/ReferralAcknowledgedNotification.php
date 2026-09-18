<?php

namespace App\Notifications;

use App\Models\Referral;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class ReferralAcknowledgedNotification extends Notification
{
    use Queueable;

    public function __construct(public Referral $referral) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Referral Acknowledged: ' . $this->referral->referral_code)
            ->greeting('Dear ' . $notifiable->name . ',')
            ->line('Your referral has been acknowledged and is now being processed by the Office of Student Services.')
            ->line('**Referral Code:** ' . $this->referral->referral_code)
            ->line('**Student:** ' . $this->referral->student->first_name . ' ' . $this->referral->student->last_name)
            ->line('**Referral Type:** ' . str_replace('_', ' ', ucfirst($this->referral->referral_type)))
            ->line('You will receive further updates as the case progresses.')
            ->salutation('iCARE — BSU Office of Student Services');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'           => 'referral_acknowledged',
            'referral_id'    => $this->referral->id,
            'referral_code'  => $this->referral->referral_code,
            'student_name'   => $this->referral->student->first_name . ' ' . $this->referral->student->last_name,
            'message'        => "Your referral {$this->referral->referral_code} has been acknowledged.",
        ];
    }
}