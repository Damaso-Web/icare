<?php

namespace App\Notifications;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// Bug: escalating a Call Slip to the Department Chair (CallSlipController::
// escalateToDeptChair()) only updated the appointment's call_slip_stage and
// wrote an audit log entry - no Department Chair ever actually found out.
// Sent to every Department Chair in the student's college, same unit-wide
// reach as UnscheduledCallSlipNotification's notifyDeanSecretaries().
class CallSlipEscalatedNotification extends Notification
{
    use Queueable;

    public function __construct(public Appointment $appointment) {}

    public function via(object $notifiable): array
    {
        return ['database', \App\Channels\LoggedMailChannel::class];
    }

    private function studentName(): string
    {
        $s = $this->appointment->student;
        return $s ? trim("{$s->first_name} {$s->last_name}") : 'A student';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Call Slip Escalated: ' . $this->studentName())
            ->greeting('Dear ' . $notifiable->name . ',')
            ->line('A Call Slip for a student from your college has been escalated to the Department Chair.')
            ->line('**Student:** ' . $this->studentName())
            ->line('**Student ID:** ' . ($this->appointment->student?->student_id ?? '-'));

        if ($this->appointment->call_slip_notes) {
            $mail->line('**Notes:** ' . $this->appointment->call_slip_notes);
        }

        return $mail->line('Please follow up with the student.')
            ->salutation('iCARE - BSU Office of Student Services');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'             => 'call_slip_escalated',
            'appointment_id'   => $this->appointment->id,
            'appointment_code' => $this->appointment->appointment_code,
            'student_name'     => $this->studentName(),
            'student_id'       => $this->appointment->student?->student_id,
            'message'          => 'Call Slip for ' . $this->studentName() . ' has been escalated to you for follow-up.',
        ];
    }
}