<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class CronController extends Controller
{
    // Fails CLOSED: if CRON_SECRET isn't configured, nobody gets in (previously a
    // missing secret meant null === null and the endpoint was open to everyone).
    private function secretOk(Request $request): bool
    {
        $secret = (string) config('app.cron_secret');
        $given  = (string) $request->header('X-Cron-Secret');

        return $secret !== '' && hash_equals($secret, $given);
    }

    public function followUpReminders(Request $request)
    {
        if (!$this->secretOk($request)) {
            abort(403, 'Unauthorized.');
        }

        Artisan::call('reminders:follow-up');

        return response()->json([
            'message' => 'Follow-up reminders executed.',
            'output'  => Artisan::output(),
        ]);
    }

    public function detectNoShows(Request $request)
{
    if (!$this->secretOk($request)) {
        abort(403, 'Unauthorized.');
    }

    Artisan::call('app:detect-missed-appointments');

    return response()->json([
        'message' => 'No-show detection executed.',
        'output'  => Artisan::output(),
    ]);
}
}