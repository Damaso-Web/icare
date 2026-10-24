<?php

namespace App\Support;

use App\Notifications\NewDeviceLoginNotification;
use Illuminate\Http\Request;

// B630: alert an account when it's signed in from a device/browser it
// hasn't used before, so it finds out about a sign-in it didn't make.
//
// "New" is judged by DeviceLabel (browser + OS, e.g. "Chrome on Windows") -
// not by IP address. An IP changes constantly for anyone on mobile data or
// switching Wi-Fi networks, so alerting on IP alone would fire on almost
// every login for a lot of people and the real signal (an unfamiliar
// device) would get lost in the noise. The IP is still included in the
// notification's own message for context.
//
// The very first login an account ever makes is never "new" against
// anything, so it's skipped - there is nothing yet to compare against, and
// alerting on it would mean every account gets a security notice the
// moment it's created.
class NewDeviceLoginAlert
{
    public static function maybeNotify($notifiable, Request $request, string $deviceLabel): void
    {
        $hasAnySession = $notifiable->tokens()->exists();
        if (!$hasAnySession) {
            return;
        }

        $seenBefore = $notifiable->tokens()->where('name', $deviceLabel)->exists();
        if ($seenBefore) {
            return;
        }

        $notifiable->notify(new NewDeviceLoginNotification($deviceLabel, $request->ip()));
    }
}