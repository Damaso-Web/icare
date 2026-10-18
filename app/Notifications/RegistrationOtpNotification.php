<?php

namespace App\Notifications;

use App\Channels\LoggedMailChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

// Email only (no in-app copy): carries the one-time password a student gets when
// they register. The code is never stored in the notification table.
class RegistrationOtpNotification extends Notification
{
    use Queueable;

    public function __construct(private string $otp, private int $minutes, private string $signInWith = 'Student ID Number') {}

    public function via(object $notifiable): array
    {
        return [LoggedMailChannel::class];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = trim(($notifiable->first_name ?? '') . ' ' . ($notifiable->last_name ?? ''));

        return (new MailMessage)
            ->subject('Your iCARE one-time password')
            ->greeting('Hello, ' . ($name !== '' ? $name : 'Student') . '.')
            ->line("Use the one-time password below as your password to sign in to iCARE with your {$this->signInWith}:")
            ->line('**One-time password:** ' . $this->otp)
            ->line("It expires in {$this->minutes} minutes. After you sign in you will be asked to change your password.")
            ->line('If you did not request this, you can ignore this email.')
            ->salutation(new HtmlString('Much appreciated,<br>iCARE'));
    }
}