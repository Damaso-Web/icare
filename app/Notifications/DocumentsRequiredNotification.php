<?php

namespace App\Notifications;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DocumentsRequiredNotification extends Notification
{
    use Queueable;

    public function __construct(public Appointment $appointment) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'                => 'documents_required',
            'appointment_id'      => $this->appointment->id,
            'appointment_code'    => $this->appointment->appointment_code,
            'required_documents'  => $this->appointment->required_documents,
            'message'             => "Please prepare the following for your appointment on {$this->appointment->appointment_date}: {$this->appointment->required_documents}",
        ];
    }
}
