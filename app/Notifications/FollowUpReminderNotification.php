<?php

namespace App\Notifications;

use App\Models\CaseFile;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class FollowUpReminderNotification extends Notification
{
    use Queueable;

    public function __construct(public CaseFile $case) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Follow-Up Due: ' . $this->case->case_number)
            ->greeting('Dear ' . $notifiable->name . ',')
            ->line('Case ' . $this->case->case_number . ' (' . $this->case->student->first_name . ' ' . $this->case->student->last_name . ') is flagged for follow-up.');

        if ($this->case->follow_up_due_date) {
            $mail->line('**Due:** ' . $this->case->follow_up_due_date->format('F j, Y'));
        }
        if ($this->case->follow_up_notes) {
            $mail->line('**Notes:** ' . $this->case->follow_up_notes);
        }

        return $mail->salutation('iCARE — BSU Office of Student Services');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'             => 'follow_up_reminder',
            'case_id'          => $this->case->id,
            'case_number'      => $this->case->case_number,
            'student_name'     => $this->case->student->first_name . ' ' . $this->case->student->last_name,
            'follow_up_due_date' => $this->case->follow_up_due_date?->toDateString(),
            'message'          => "Case {$this->case->case_number} is due for follow-up" . ($this->case->follow_up_due_date ? " on {$this->case->follow_up_due_date->format('M j, Y')}" : '') . '.',
        ];
    }
}