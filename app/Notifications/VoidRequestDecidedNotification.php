<?php

namespace App\Notifications;

use App\Models\Referral;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

// Sent to the referrer once GCU/SDU/TMDU approves (archives) or denies their
// cancellation/void request - see ReferralController::approveVoid()/denyVoid().
class VoidRequestDecidedNotification extends Notification
{
    use Queueable, \App\Notifications\Concerns\SendsEmail;

    public function __construct(public Referral $referral, public bool $approved, public ?string $denialNote = null) {}

    public function toArray(object $notifiable): array
    {
        $studentName = $this->referral->student->first_name . ' ' . $this->referral->student->last_name;
        $message = $this->approved
            ? "Your request to cancel your referral for {$studentName} was approved. It has been archived."
            : "Your request to cancel your referral for {$studentName} was denied." . ($this->denialNote ? " Reason: {$this->denialNote}" : '');

        return [
            'type'           => 'void_decided',
            'referral_id'    => $this->referral->id,
            'referral_code'  => $this->referral->referral_code,
            'approved'       => $this->approved,
            'message'        => $message,
        ];
    }
}