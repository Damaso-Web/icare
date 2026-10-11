<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

// Sent to the STUDENT asking them to fill out the forms in My Account:
// automatically when their Student Information File is first opened, and
// again whenever GCU sends a reminder from the SIF.
class FillOutFormsNotification extends Notification
{
    use Queueable;

    /** @param bool $reminder true when GCU staff sent it by hand */
    public function __construct(public bool $reminder = false) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'    => 'fill_out_forms',
            'message' => $this->reminder
                ? 'Reminder from the Guidance and Counseling Unit: please fill out the forms in My Account as soon as you can.'
                : 'A Student Information File has been opened for you at the Office of Student Services. Please go to My Account and fill out the forms there.',
        ];
    }
}
