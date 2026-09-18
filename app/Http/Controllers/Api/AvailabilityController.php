<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use Illuminate\Http\Request;

class AvailabilityController extends Controller
{
    private const SLOTS = ['08:00', '09:00', '10:00', '11:00', '13:00', '14:00', '15:00'];

    // Returns a Mon-Fri grid of 1-hour slots for the given week, marking each as
    // available or booked for the appointment's unit. Used by the public
    // scheduling page so students pick straight from what's open (B69-71).
    public function weekGrid(Request $request, $token)
    {
        $appointment = Appointment::where('scheduling_token', $token)->firstOrFail();

        $request->validate([
            'week_start' => 'required|date',
        ]);

        $weekStart = new \DateTime($request->week_start);
        $weekStart->modify('monday this week');

        $bookedRows = Appointment::where('unit', $appointment->unit)
            ->where('id', '!=', $appointment->id)
            ->whereNotIn('status', ['cancelled'])
            ->where('request_status', '!=', 'awaiting_student')
            ->whereBetween('appointment_date', [
                $weekStart->format('Y-m-d'),
                (clone $weekStart)->modify('+4 days')->format('Y-m-d'),
            ])
            ->get(['appointment_date', 'start_time', 'end_time']);

        $days = [];
        for ($i = 0; $i < 5; $i++) {
            $date = (clone $weekStart)->modify("+{$i} days");
            $dateStr = $date->format('Y-m-d');

            $slots = [];
            foreach (self::SLOTS as $slot) {
                $slotEnd = date('H:i', strtotime($slot) + 3600);
                $isBooked = $bookedRows->contains(function ($appt) use ($dateStr, $slot, $slotEnd) {
                    return $appt->appointment_date->format('Y-m-d') === $dateStr
                        && $slot < $appt->end_time
                        && $slotEnd > $appt->start_time;
                });

                $slots[] = [
                    'start'     => $slot,
                    'end'       => $slotEnd,
                    'available' => !$isBooked,
                ];
            }

            $days[] = [
                'date'  => $dateStr,
                'label' => $date->format('D, M j'),
                'slots' => $slots,
            ];
        }

        return response()->json([
            'week_start' => $weekStart->format('Y-m-d'),
            'days'       => $days,
        ]);
    }
}