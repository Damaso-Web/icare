<?php

namespace App\Notifications;

use App\Models\ParentConferenceSlip;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// Sent to the student when GCU issues a Parent Conference Slip for their
// case, so they know their parent/guardian is being asked to come in - the
// in-app (database) notice the appointment notifications use, plus an email
// so the student sees it even if they don't check iCARE.
class ParentConferenceSlipNotification extends Notification
{
    use Queueable;

    public function __construct(public ParentConferenceSlip $slip) {}

    public function via(object $notifiable): array
    {
        return ['database', \App\Channels\LoggedMailChannel::class];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $date = $this->slip->conference_date?->format('F j, Y');
        $time = $this->slip->conference_time
            ? Carbon::parse($this->slip->conference_time)->format('g:i A')
            : null;

        return (new MailMessage)
            ->subject('Parent Conference Slip Issued')
            ->greeting('Dear ' . $notifiable->first_name . ',')
            ->line('A Parent Conference Slip was issued for your case.')
            ->line('Your parent/guardian is asked to come to the office on ' . $date . ($time ? " at {$time}" : '') . '.')
            ->line('**Reason:** ' . $this->slip->reason)
            ->salutation('iCARE - BSU Office of Student Services');
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