<?php

namespace App\Notifications;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AppointmentConfirmedNotification extends Notification
{
    use Queueable;

    public function __construct(public Appointment $appointment) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'              => 'appointment_confirmed',
            'appointment_id'    => $this->appointment->id,
            'appointment_code'  => $this->appointment->appointment_code,
            'appointment_date'  => $this->appointment->appointment_date,
            'start_time'        => $this->appointment->start_time,
            'message'           => "Your appointment {$this->appointment->appointment_code} on {$this->appointment->appointment_date} at {$this->appointment->start_time} has been confirmed.",
        ];
    }
}