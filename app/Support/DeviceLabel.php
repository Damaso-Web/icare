<?php

namespace App\Support;

use Illuminate\Http\Request;

// B569: Sanctum tokens were all created with the same hard-coded name
// ("icare-token" / "student-token"), so an "Active Sessions / Linked
// Devices" list would show the account signed in several times with no
// way to tell which row is which browser or device. This turns the
// request's User-Agent into a short, readable label ("Chrome on Windows",
// "Safari on iPhone") at the moment the token is created, so later a
// session list has something a person can actually recognize.
class DeviceLabel
{
    public static function fromRequest(Request $request): string
    {
        $ua = (string) $request->userAgent();
        if ($ua === '') {
            return 'Unknown device';
        }

        $os = match (true) {
            str_contains($ua, 'iPhone')            => 'iPhone',
            str_contains($ua, 'iPad')               => 'iPad',
            str_contains($ua, 'Android')             => 'Android',
            str_contains($ua, 'Windows')              => 'Windows',
            str_contains($ua, 'Macintosh')             => 'Mac',
            str_contains($ua, 'Linux')                  => 'Linux',
            default => null,
        };

        $browser = match (true) {
            str_contains($ua, 'Edg/')      => 'Edge',
            str_contains($ua, 'OPR/')      => 'Opera',
            str_contains($ua, 'Chrome/')   => 'Chrome',
            str_contains($ua, 'CriOS/')    => 'Chrome',
            str_contains($ua, 'FxiOS/')    => 'Firefox',
            str_contains($ua, 'Firefox/')  => 'Firefox',
            str_contains($ua, 'Safari/') && str_contains($ua, 'Version/') => 'Safari',
            default => null,
        };

        if ($browser && $os) return "{$browser} on {$os}";
        if ($browser)        return $browser;
        if ($os)              return $os;
        return 'Unknown device';
    }
}