<?php

namespace App\Notifications;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

// B534: tells the student their appointment was auto-cancelled because its date
// was declared a holiday/class suspension, and points them to the Appointments
// page to request a new schedule (there is no separate "rebooking" page/token
// for an existing case - this is the same place every student requests one).
class AppointmentHolidayCancelledNotification extends Notification
{
    use Queueable, \App\Notifications\Concerns\SendsEmail;

    public function __construct(public Appointment $appointment, public string $holidayName) {}

    public function toArray(object $notifiable): array
    {
        return [
            'type'             => 'appointment_holiday_cancelled',
            'appointment_id'   => $this->appointment->id,
            'appointment_code' => $this->appointment->appointment_code,
            'message'          => "Your appointment {$this->appointment->appointment_code} was cancelled because \"{$this->holidayName}\" was declared on that date. Please request a new appointment.",
            'link'             => '/student/appointments',
        ];
    }
}