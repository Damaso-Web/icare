<?php

namespace App\Notifications;

use App\Models\Referral;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/** In-app notice for the staff member a referral/case has just been assigned or reassigned to. */
class ReferralAssignedNotification extends Notification
{
    use Queueable, \App\Notifications\Concerns\SendsEmail;

    public function __construct(public Referral $referral, public ?string $assignedBy = null) {}


    public function toArray(object $notifiable): array
    {
        $student = $this->referral->student;
        $name    = $student ? trim($student->first_name . ' ' . $student->last_name) : 'a student';
        $case    = $this->referral->case;

        return [
            'type'          => 'referral_assigned',
            'referral_id'   => $this->referral->id,
            'referral_code' => $this->referral->referral_code,
            'case_id'       => $case?->id,
            'case_number'   => $case?->case_number,
            'student_name'  => $name,
            'message'       => "Referral {$this->referral->referral_code}" . ($case ? " (case {$case->case_number})" : '') . " for {$name} has been assigned to you" . ($this->assignedBy ? " by {$this->assignedBy}" : '') . '.',
        ];
    }
}