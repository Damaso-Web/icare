<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class CronController extends Controller
{
    public function followUpReminders(Request $request)
    {
        if ($request->header('X-Cron-Secret') !== config('app.cron_secret')) {
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
        if ($request->header('X-Cron-Secret') !== config('app.cron_secret')) {
            abort(403, 'Unauthorized.');
        }

        Artisan::call('appointments:detect-no-show');

        return response()->json([
            'message' => 'No-show detection executed.',
            'output'  => Artisan::output(),
        ]);
    }
}