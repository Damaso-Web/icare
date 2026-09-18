<?php

namespace App\Notifications;

use App\Models\Referral;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class ReferralSubmittedToCollegeNotification extends Notification
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
            ->subject('New Referral for Your College: ' . $this->referral->referral_code)
            ->greeting('Dear ' . $notifiable->name . ',')
            ->line('A student from your college has been referred to the Office of Student Services.')
            ->line('**Referral Code:** ' . $this->referral->referral_code)
            ->line('**Student:** ' . $this->referral->student->first_name . ' ' . $this->referral->student->last_name)
            ->line('**Referral Type:** ' . str_replace('_', ' ', ucfirst($this->referral->referral_type)))
            ->line('**Urgency:** ' . ucfirst($this->referral->urgency_level))
            ->salutation('iCARE — BSU Office of Student Services');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'          => 'referral_submitted_college',
            'referral_id'   => $this->referral->id,
            'referral_code' => $this->referral->referral_code,
            'student_name'  => $this->referral->student->first_name . ' ' . $this->referral->student->last_name,
            'message'       => "New referral {$this->referral->referral_code} submitted for a student in your college.",
        ];
    }
}