<?php

namespace App\Notifications;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class PrepareDocumentsNotification extends Notification
{
    use Queueable;

    public function __construct(public Appointment $appointment) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Documents to Prepare for Your Appointment')
            ->greeting('Dear ' . $notifiable->first_name . ',')
            ->line('Your appointment on ' . $this->appointment->appointment_date->format('F j, Y') . ' at ' . $this->appointment->start_time . ' has been confirmed.')
            ->line('Please bring the following document(s):');

        foreach ($this->appointment->required_documents as $doc) {
            $mail->line('• ' . $doc);
        }

        return $mail->salutation('iCARE — BSU Office of Student Services');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'             => 'prepare_documents',
            'appointment_id'   => $this->appointment->id,
            'appointment_code' => $this->appointment->appointment_code,
            'documents'        => $this->appointment->required_documents,
            'message'          => 'Please prepare the required documents for your upcoming appointment.',
        ];
    }
}