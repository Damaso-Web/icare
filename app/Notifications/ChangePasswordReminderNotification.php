<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

// In-app reminder shown in a student's bell while they are still using a
// temporary / one-time password.
class ChangePasswordReminderNotification extends Notification
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'    => 'change_password',
            'message' => 'Please change your password. Go to My Account to set a new one.',
        ];
    }
}