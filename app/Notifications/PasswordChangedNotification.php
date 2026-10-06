<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Security notice left in the account's own notification bell whenever its
 * password is changed, so an owner who did not make the change finds out.
 */
class PasswordChangedNotification extends Notification
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'    => 'password_changed',
            'message' => 'Your password was changed on ' . now()->format('M j, Y \a\t g:i A')
                . '. If this was not you, contact the OSS administrator right away.',
        ];
    }
}
