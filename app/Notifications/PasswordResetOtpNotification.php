<?php

namespace App\Notifications;

use App\Channels\LoggedMailChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

// Email only: the 6-digit code for "Forgot password". There is no link in it on
// purpose, so there is nothing to click and nothing for a look-alike email to copy.
class PasswordResetOtpNotification extends Notification
{
    use Queueable;

    public function __construct(private string $otp, private int $minutes) {}

    public function via(object $notifiable): array
    {
        return [LoggedMailChannel::class];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your iCARE password reset code')
            ->greeting('Hello,')
            ->line('We received a request to reset the password of your iCARE account.')
            ->line('**Your reset code:** ' . $this->otp)
            ->line("Enter this code on the iCARE reset page. It expires in {$this->minutes} minutes and can be used once.")
            ->line('iCARE will never send you a link to reset your password or ask for this code by message or call. If you did not ask for this, ignore this email - your password will not change.')
            ->salutation(new HtmlString('iCARE - BSU Office of Student Services'));
    }
}