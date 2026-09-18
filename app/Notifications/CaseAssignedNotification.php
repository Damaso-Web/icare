<?php

namespace App\Notifications;

use App\Models\CaseFile;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class CaseAssignedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public CaseFile $case,
        public string $reason,
        public ?string $notes = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Case Assigned to You: ' . $this->case->case_number)
            ->greeting('Dear ' . $notifiable->name . ',')
            ->line($this->reason)
            ->line('**Case Number:** ' . $this->case->case_number)
            ->line('**Student:** ' . $this->case->student->first_name . ' ' . $this->case->student->last_name)
            ->line('**Unit:** ' . $this->case->current_unit);

        if ($this->notes) {
            $mail->line('**Notes:** ' . $this->notes);
        }

        return $mail->salutation('iCARE — BSU Office of Student Services');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'        => 'case_assigned',
            'case_id'     => $this->case->id,
            'case_number' => $this->case->case_number,
            'student_name'=> $this->case->student->first_name . ' ' . $this->case->student->last_name,
            'message'     => $this->reason,
        ];
    }
}