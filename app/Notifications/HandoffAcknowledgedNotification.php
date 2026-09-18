<?php

namespace App\Notifications;

use App\Models\CaseHandoff;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class HandoffAcknowledgedNotification extends Notification
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
            'type'       => 'handoff_acknowledged',
            'case_id'    => $this->handoff->case_id,
            'handoff_id' => $this->handoff->id,
            'to_unit'    => $this->handoff->to_unit,
            'message'    => "{$this->handoff->to_unit} confirmed receipt of the case you handed off.",
        ];
    }
}
