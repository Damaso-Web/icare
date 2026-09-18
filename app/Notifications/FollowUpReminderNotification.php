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
            ->subject('Follow-Up Reminder: ' . $this->case->case_number)
            ->greeting('Dear ' . $notifiable->name . ',')
            ->line('This is a reminder that the following case is still flagged as needing follow-up.')
            ->line('**Case Number:** ' . $this->case->case_number)
            ->line('**Student:** ' . $this->case->student->first_name . ' ' . $this->case->student->last_name)
            ->line('**Flagged Since:** ' . $this->case->follow_up_flagged_at?->format('M d, Y'));

        if ($this->case->follow_up_notes) {
            $mail->line('**Notes:** ' . $this->case->follow_up_notes);
        }

        return $mail->line('Please review and take the necessary action, or mark the follow-up as resolved.')
            ->salutation('iCARE — BSU Office of Student Services');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'        => 'follow_up_reminder',
            'case_id'     => $this->case->id,
            'case_number' => $this->case->case_number,
            'student_name'=> $this->case->student->first_name . ' ' . $this->case->student->last_name,
            'message'     => "Reminder: Case {$this->case->case_number} still needs follow-up.",
        ];
    }
}