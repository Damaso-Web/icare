<?php

namespace App\Notifications;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// Sent to the Dean's Secretary when GCU sends a Call Slip for a student who
// was asked to pick a schedule (referral acknowledged) but never did.
class UnscheduledCallSlipNotification extends Notification
{
    use Queueable;

    public function __construct(public Appointment $appointment) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    private function studentName(): string
    {
        $s = $this->appointment->student;
        return $s ? trim("{$s->first_name} {$s->last_name}") : 'A student';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Call Slip: ' . $this->studentName() . ' has not picked a schedule')
            ->greeting('Dear ' . $notifiable->name . ',')
            ->line('A student from your college was asked to pick an appointment schedule but has not done so yet.')
            ->line('**Student:** ' . $this->studentName())
            ->line('**Student ID:** ' . ($this->appointment->student?->student_id ?? '-'))
            ->line('**Appointment Type:** ' . $this->appointment->appointment_type)
            ->line('Please remind the student to pick a schedule through the link sent to them.')
            ->salutation('iCARE - BSU Office of Student Services');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'             => 'call_slip_unscheduled',
            'appointment_id'   => $this->appointment->id,
            'appointment_code' => $this->appointment->appointment_code,
            'student_name'     => $this->studentName(),
            'student_id'       => $this->appointment->student?->student_id,
            'message'          => $this->studentName() . ' has not picked a schedule for their appointment. A Call Slip was sent so you can follow up.',
        ];
    }
}