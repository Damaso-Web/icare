<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// Email sent by "Forgot password". The link opens the iCARE reset page on the
// frontend; `type` tells it whether the account is a staff user or a student.
class ResetPasswordLinkNotification extends Notification
{
    public function __construct(public string $token, public string $type = 'staff') {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $base = rtrim((string) config('app.frontend_url'), '/');
        $url  = $base . '/reset-password?' . http_build_query([
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
            'type'  => $this->type,
        ]);
        $minutes = (int) config('auth.passwords.' . ($this->type === 'student' ? 'students' : 'users') . '.expire', 60);

        return (new MailMessage)
            ->subject('Reset your iCARE password')
            ->greeting('Hello,')
            ->line('We received a request to reset the password of your iCARE account.')
            ->action('Reset Password', $url)
            ->line("This link expires in {$minutes} minutes and can be used once.")
            ->line('If you did not ask for this, you can ignore this email - your password will not change.')
            ->salutation('iCARE - BSU Office of Student Services');
    }
}