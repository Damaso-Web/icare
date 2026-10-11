<?php

namespace App\Notifications;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

// Sent to the STUDENT ahead of a confirmed appointment: once the day before
// ("tomorrow") and once a few hours before ("today"). See the
// reminders:appointments command, which also withdraws a reminder whose
// appointment was cancelled or moved.
class AppointmentReminderNotification extends Notification
{
    use Queueable;

    /** @param string $kind 'tomorrow' or 'today' */
    public function __construct(public Appointment $appointment, public string $kind) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** The exact date and time this reminder is for - a moved appointment gets a new one. */
    public static function slot(Appointment $a): string
    {
        return $a->appointment_date?->format('Y-m-d') . ' ' . substr((string) $a->start_time, 0, 5);
    }

    public function toArray(object $notifiable): array
    {
        $a     = $this->appointment;
        $time  = date('g:i A', strtotime((string) $a->start_time));
        $what  = ucwords(str_replace('_', ' ', (string) $a->appointment_type));
        $with  = $a->staff?->name ? " with {$a->staff->name}" : '';
        $where = $a->location ? " at {$a->location}" : '';

        return [
            'type'           => 'appointment_reminder',
            'kind'           => $this->kind,
            'appointment_id' => $a->id,
            'slot'           => self::slot($a),
            'message'        => 'Reminder: You have an appointment ' . ($this->kind === 'today' ? 'today' : 'tomorrow')
                . " at {$time} ({$what}{$with}{$where}).",
        ];
    }
}
