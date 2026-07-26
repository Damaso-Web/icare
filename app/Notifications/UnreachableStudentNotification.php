<?php

namespace App\Notifications;

use App\Models\CaseFile;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class UnreachableStudentNotification extends Notification
{
    use Queueable;

    public function __construct(public CaseFile $case, public string $notes = '') {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Unreachable Student Alert: ' . $this->case->student->first_name . ' ' . $this->case->student->last_name)
            ->greeting('Dear ' . $notifiable->name . ',')
            ->line('The Guidance and Counseling Unit has been unable to reach the following student.')
            ->line('**Student:** ' . $this->case->student->first_name . ' ' . $this->case->student->last_name)
            ->line('**Student ID:** ' . $this->case->student->student_id)
            ->line('**College:** ' . $this->case->student->college)
            ->line('**Case Number:** ' . $this->case->case_number)
            ->line('**Notes:** ' . ($this->notes ?: 'No additional notes.'))
            ->line('Please assist in locating or contacting this student.')
            ->salutation('iCARE — BSU Office of Student Services');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'         => 'unreachable_student',
            'case_id'      => $this->case->id,
            'case_number'  => $this->case->case_number,
            'student_name' => $this->case->student->first_name . ' ' . $this->case->student->last_name,
            'student_id'   => $this->case->student->student_id,
            'college'      => $this->case->student->college,
            'notes'        => $this->notes,
            'message'      => 'Student ' . $this->case->student->first_name . ' ' . $this->case->student->last_name . ' is unreachable. Please assist in contacting them.',
        ];
    }
}