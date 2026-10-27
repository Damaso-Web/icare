<?php

namespace App\Notifications;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AppointmentConfirmedNotification extends Notification
{
    use Queueable, \App\Notifications\Concerns\SendsEmail;

    /** @param bool $forReferrer true when this goes to whoever referred the student (instructor, dean), not the student */
    public function __construct(public Appointment $appointment, public bool $forReferrer = false) {}


    public function toArray(object $notifiable): array
    {
        // Bug (10/27/2026): interpolating appointment_date directly dropped
        // in its raw datetime string (e.g. "2026-10-29 00:00:00", a
        // misleading midnight) and never mentioned the time at all - fixed
        // to a plain date and a 12-hour time with AM/PM, same as every
        // other appointment notification (see AppointmentRequestedNotification).
        $date = $this->appointment->appointment_date
            ? \Carbon\Carbon::parse($this->appointment->appointment_date)->format('F j, Y')
            : '';
        $time = $this->appointment->start_time
            ? \Carbon\Carbon::parse($this->appointment->start_time)->format('g:i A')
            : '';
        $when = trim("{$date} {$time}");

        return [
            'type'              => 'appointment_confirmed',
            'appointment_id'    => $this->appointment->id,
            'appointment_code'  => $this->appointment->appointment_code,
            'appointment_date'  => $date,
            'start_time'        => $time,
            'message'           => $this->forReferrer
                ? "An appointment {$this->appointment->appointment_code} has been set on {$when} for the student you referred."
                : "Your appointment {$this->appointment->appointment_code} on {$when} has been confirmed.",
        ];
    }
}