<?php

namespace App\Support;

use App\Models\CalendarEntry;
use Illuminate\Validation\ValidationException;

/**
 * Active Holidays and Suspensions block their date for appointments.
 * Archived entries never block. Used by every endpoint that books or moves an appointment.
 */
class BlockedDates
{
    /** "This date is not available: Holiday." or null when the date is open. */
    public static function message(?string $date): ?string
    {
        if (!$date) return null;
        $entries = CalendarEntry::where('status', 'active')->whereDate('entry_date', substr($date, 0, 10))->get();
        if ($entries->isEmpty()) return null;
        return 'This date is not available: ' . $entries->map(fn($e) => $e->type_label . ' (' . $e->name . ')')->implode(', ') . '.';
    }

    /** Throws a 422 validation error on $field when the date is blocked. */
    public static function assertOpen(?string $date, string $field = 'appointment_date'): void
    {
        if ($msg = self::message($date)) {
            throw ValidationException::withMessages([$field => [$msg]]);
        }
    }

    /** [date => ['type' => 'holiday', 'label' => "Holiday: All Saints' Day", 'name' => ...]] for a range. */
    public static function between(string $from, string $to): array
    {
        $out = [];
        foreach (CalendarEntry::where('status', 'active')->whereBetween('entry_date', [$from, $to])->orderBy('entry_date')->get() as $e) {
            $d = $e->entry_date->format('Y-m-d');
            $label = $e->type_label . ': ' . $e->name;
            $out[$d] = isset($out[$d])
                ? ['type' => $out[$d]['type'], 'name' => $out[$d]['name'] . '; ' . $e->name, 'label' => $out[$d]['label'] . '; ' . $label]
                : ['type' => $e->type, 'name' => $e->name, 'label' => $label];
        }
        return $out;
    }
}