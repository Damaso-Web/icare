<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// Tells an account owner that an administrator reset their password.
// The temporary password itself is never emailed.
class AdminPasswordResetNotification extends Notification
{
    public function __construct(public string $adminName = 'An administrator') {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $when = now()->format('F j, Y \a\t g:i A');
        $mail = (new MailMessage)
            ->subject('Your iCARE password was reset')
            ->greeting('Hello,')
            ->line("An administrator reset the password of your iCARE account on {$when}.")
            ->line('You were signed out of all devices. The Office of Student Services will give you a temporary password; you will be asked to choose a new password the next time you sign in.')
            ->line('If you did not expect this, contact the Office of Student Services right away.')
            ->salutation('iCARE - BSU Office of Student Services');
        return \App\Support\MailTracking::off($mail);
    }
}