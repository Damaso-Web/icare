<?php

namespace App\Notifications;

use App\Models\Complaint;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

// Sent to the SDU Head and the student's Dean when a complaint (Incident
// Report) is filed. Complaints used to reuse NewReferralNotification and
// talk about a "referral", which is confusing since the recipient is
// opening a complaint, not a referral - this gives it its own wording.
class NewComplaintNotification extends Notification
{
    use Queueable, \App\Notifications\Concerns\SendsEmail;

    public function __construct(public Complaint $complaint) {}

    public function toArray(object $notifiable): array
    {
        $student = $this->complaint->complainee;
        $name    = $student ? $student->first_name . ' ' . $student->last_name : 'a student';

        return [
            'type'           => 'new_complaint',
            'complaint_id'   => $this->complaint->id,
            'complaint_code' => $this->complaint->complaint_code,
            'referral_id'    => $this->complaint->referral?->id,
            'student_name'   => $name,
            'message'        => "New notification: a complaint ({$this->complaint->complaint_code}) was filed against {$name}.",
        ];
    }
}