<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * B630: a security notice left in the account's own notification bell
 * whenever it signs in from a device/browser that hasn't signed into this
 * account before, so an owner who didn't do it finds out. Mirrors
 * PasswordChangedNotification's shape - database-only, one plain message.
 */
class NewDeviceLoginNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $device,
        public ?string $ip,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $where = $this->ip ? " (IP {$this->ip})" : '';
        return [
            'type'    => 'new_device_login',
            'message' => "New sign-in detected from {$this->device}{$where} at " . now()->format('M j, Y \a\t g:i A')
                . '. If this was not you, change your password and sign out that device from Active Sessions.',
        ];
    }
}