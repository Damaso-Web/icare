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
        return [
            'type'              => 'appointment_confirmed',
            'appointment_id'    => $this->appointment->id,
            'appointment_code'  => $this->appointment->appointment_code,
            'appointment_date'  => $this->appointment->appointment_date,
            'start_time'        => $this->appointment->start_time,
            'message'           => $this->forReferrer
                ? "An appointment {$this->appointment->appointment_code} has been set on {$this->appointment->appointment_date} for the student you referred."
                : "Your appointment {$this->appointment->appointment_code} on {$this->appointment->appointment_date} has been confirmed.",
        ];
    }
}