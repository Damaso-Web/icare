<?php

namespace App\Notifications;

use App\Models\CaseHandoff;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CaseHandoffNotification extends Notification
{
    use Queueable;

    public function __construct(public CaseHandoff $handoff) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'       => 'case_handoff',
            'case_id'    => $this->handoff->case_id,
            'handoff_id' => $this->handoff->id,
            'from_unit'  => $this->handoff->from_unit,
            'to_unit'    => $this->handoff->to_unit,
            'message'    => "A case has been handed off to {$this->handoff->to_unit} for you: {$this->handoff->reason}",
        ];
    }
}
