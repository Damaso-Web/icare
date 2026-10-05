<?php

namespace App\Notifications;

use App\Models\ParentConferenceSlip;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

// Sent to the student when GCU issues a Parent Conference Slip for their
// case, so they know their parent/guardian is being asked to come in - same
// in-app (database) channel the appointment notifications use.
class ParentConferenceSlipNotification extends Notification
{
    use Queueable;

    public function __construct(public ParentConferenceSlip $slip) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $date = $this->slip->conference_date?->format('F j, Y');
        $time = $this->slip->conference_time
            ? Carbon::parse($this->slip->conference_time)->format('g:i A')
            : null;

        return [
            'type'            => 'parent_conference_slip',
            'slip_id'         => $this->slip->id,
            'case_id'         => $this->slip->case_id,
            'conference_date' => $this->slip->conference_date?->toDateString(),
            'conference_time' => $this->slip->conference_time,
            'message'         => "A Parent Conference Slip was issued for you. Your parent/guardian is asked to come to the office on {$date}" . ($time ? " at {$time}" : '') . ". Reason: {$this->slip->reason}.",
        ];
    }
}