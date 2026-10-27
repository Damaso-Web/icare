<?php

namespace App\Notifications;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DocumentsRequiredNotification extends Notification
{
    use Queueable, \App\Notifications\Concerns\SendsEmail;

    public function __construct(public Appointment $appointment) {}


    public function toArray(object $notifiable): array
    {
        // Same date/time clarity fix as AppointmentConfirmedNotification -
        // a raw datetime string with no AM/PM, and the time was missing
        // entirely.
        $date = $this->appointment->appointment_date
            ? \Carbon\Carbon::parse($this->appointment->appointment_date)->format('F j, Y')
            : '';
        $time = $this->appointment->start_time
            ? \Carbon\Carbon::parse($this->appointment->start_time)->format('g:i A')
            : '';
        $when = trim("{$date} {$time}");

        return [
            'type'                => 'documents_required',
            'appointment_id'      => $this->appointment->id,
            'appointment_code'    => $this->appointment->appointment_code,
            'required_documents'  => $this->appointment->required_documents,
            'message'             => "Please prepare the following for your appointment on {$when}: {$this->appointment->required_documents}",
        ];
    }
}