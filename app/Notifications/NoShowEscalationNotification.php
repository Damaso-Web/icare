<?php

namespace App\Notifications;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class NoShowEscalationNotification extends Notification
{
    use Queueable;

    public function __construct(public Appointment $appointment) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('No-Show Alert: ' . $this->appointment->student->first_name . ' ' . $this->appointment->student->last_name)
            ->greeting('Dear ' . $notifiable->name . ',')
            ->line('A student from your college did not attend their scheduled appointment.')
            ->line('**Student:** ' . $this->appointment->student->first_name . ' ' . $this->appointment->student->last_name)
            ->line('**Student ID:** ' . $this->appointment->student->student_id)
            ->line('**Appointment Date:** ' . $this->appointment->appointment_date)
            ->line('**Appointment Type:** ' . $this->appointment->appointment_type)
            ->line('Please coordinate with the student regarding their missed appointment.')
            ->salutation('iCARE — BSU Office of Student Services');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'              => 'no_show_escalation',
            'appointment_id'    => $this->appointment->id,
            'appointment_code'  => $this->appointment->appointment_code,
            'student_name'      => $this->appointment->student->first_name . ' ' . $this->appointment->student->last_name,
            'student_id'        => $this->appointment->student->student_id,
            'appointment_date'  => $this->appointment->appointment_date,
            'appointment_type'  => $this->appointment->appointment_type,
            'message'           => 'Student ' . $this->appointment->student->first_name . ' ' . $this->appointment->student->last_name . ' did not attend their appointment on ' . $this->appointment->appointment_date . '.',
        ];
    }
}