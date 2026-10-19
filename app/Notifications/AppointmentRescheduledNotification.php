<?php

namespace App\Notifications;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

// Tells the student their appointment was rescheduled, why, and that they must pick a new schedule.
// data.appointment_id opens the appointment page where the new schedule is chosen.
class AppointmentRescheduledNotification extends Notification
{
    use Queueable, \App\Notifications\Concerns\SendsEmail;

    public function __construct(public Appointment $appointment, public string $reason) {}

    public function toArray(object $notifiable): array
    {
        $reason = rtrim(trim($this->reason), '.');
        return [
            'type'             => 'appointment_rescheduled',
            'appointment_id'   => $this->appointment->id,
            'appointment_code' => $this->appointment->appointment_code,
            'status'           => $this->appointment->request_status,
            'reason'           => $this->reason,
            'message'          => "Your appointment {$this->appointment->appointment_code} has been rescheduled due to {$reason}. Please set a new appointment schedule.",
            'link'             => '/student/appointments/' . $this->appointment->id,
        ];
    }
}