<?php

namespace App\Notifications;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

// Sent to the student when TMDU puts their PAR release on hold - they are
// asked to go to the TMDU office and collect their PAR results.
class ParOnHoldNotification extends Notification
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
            'type'           => 'par_on_hold',
            'appointment_id' => $this->appointment->id,
            'message'        => 'Your PAR release is on hold. Please go to the TMDU office and collect your Psychological Assessment Report (PAR) results.',
        ];
    }
}