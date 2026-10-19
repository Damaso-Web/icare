<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Support\BlockedDates;
use Illuminate\Http\Request;

/** Dates blocked by active Holidays and Suspensions, for every appointment date picker. */
class CalendarBlockedDatesController extends Controller
{
    public function index(Request $request)
    {
        return response()->json($this->payload($request));
    }

    // The scheduling link (no login) needs the same list; the token proves the link is real.
    public function publicIndex(Request $request, string $token)
    {
        Appointment::where('scheduling_token', $token)->where('token_expires_at', '>=', now())->firstOrFail();
        return response()->json($this->payload($request));
    }

    private function payload(Request $request): array
    {
        $data = $request->validate(['from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from']);
        $from = $data['from'] ?? now()->toDateString();
        $to   = $data['to'] ?? now()->addYear()->toDateString();
        return ['blocked' => BlockedDates::between(substr($from, 0, 10), substr($to, 0, 10))];
    }
}