<?php

namespace App\Notifications;

use App\Models\TestingRecord;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class TesterAssignedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public TestingRecord $testingRecord
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Testing Record Assigned to You: #' . $this->testingRecord->id)
            ->greeting('Dear ' . $notifiable->name . ',')
            ->line('You have been assigned as the tester for a psychological testing record.')
            ->line('**Student:** ' . $this->testingRecord->student->first_name . ' ' . $this->testingRecord->student->last_name)
            ->line('You can now proceed with this record\'s testing workflow.')
            ->salutation('iCARE — BSU Office of Student Services');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'              => 'tester_assigned',
            'testing_record_id' => $this->testingRecord->id,
            'student_name'      => $this->testingRecord->student->first_name . ' ' . $this->testingRecord->student->last_name,
            'message'           => 'You have been assigned as the tester for testing record #' . $this->testingRecord->id . '.',
        ];
    }
}