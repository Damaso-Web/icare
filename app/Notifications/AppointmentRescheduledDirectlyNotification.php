<?php

namespace App\Notifications;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

// Tells the student staff already moved their appointment to a new date/time
// directly (via "Reschedule Now") - unlike AppointmentRescheduledNotification,
// this needs no action from the student: the new slot is already locked in.
class AppointmentRescheduledDirectlyNotification extends Notification
{
    use Queueable, \App\Notifications\Concerns\SendsEmail;

    public function __construct(public Appointment $appointment, public string $reason) {}

    public function toArray(object $notifiable): array
    {
        $date = $this->appointment->appointment_date
            ? \Carbon\Carbon::parse($this->appointment->appointment_date)->format('F j, Y')
            : '';
        $time = $this->appointment->start_time
            ? \Carbon\Carbon::parse($this->appointment->start_time)->format('g:i A')
            : '';
        $when = trim("{$date} {$time}");
        $reason = rtrim(trim($this->reason), '.');

        return [
            'type'             => 'appointment_rescheduled_directly',
            'appointment_id'   => $this->appointment->id,
            'appointment_code' => $this->appointment->appointment_code,
            'appointment_date' => $date,
            'start_time'       => $time,
            'reason'           => $this->reason,
            'message'          => "Your appointment {$this->appointment->appointment_code} has been rescheduled to {$when} due to {$reason}.",
        ];
    }
}