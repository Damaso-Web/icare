<?php

namespace App\Notifications;

use App\Models\Appointment;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

// In-app notice to the office when a student picks a schedule (or asks to
// reschedule), so staff know there is a request waiting for confirmation.
class AppointmentRequestedNotification extends Notification
{
    use Queueable;

    public function __construct(public Appointment $appointment, public string $kind = 'scheduled') {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $a = $this->appointment->loadMissing('student');
        $s = $a->student;
        $name = $s ? trim("{$s->first_name} {$s->last_name}") : 'A student';

        if ($this->kind === 'reschedule_requested') {
            $message = "{$name} requested to reschedule appointment {$a->appointment_code}. Waiting for them to pick a new slot.";
        } else {
            $date = $a->appointment_date ? Carbon::parse($a->appointment_date)->format('F j, Y') : '';
            $time = $a->start_time ? Carbon::parse($a->start_time)->format('g:i A') : '';
            $message = "{$name} set a schedule for appointment {$a->appointment_code}: {$date} {$time}. Please confirm it.";
        }

        return [
            'type'           => 'appointment_requested',
            'kind'           => $this->kind,
            'appointment_id' => $a->id,
            'message'        => $message,
        ];
    }
}