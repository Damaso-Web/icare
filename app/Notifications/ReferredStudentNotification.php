<?php

namespace App\Notifications;

use App\Models\Referral;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

// Sent to the STUDENT every time someone refers them. The email tells them who
// referred them and to which unit, gives their login (Student ID Number and the
// temporary password, while they have not changed it yet) and asks them to update
// My Account. There is deliberately no link or button in the email.
class ReferredStudentNotification extends Notification
{
    use Queueable, \App\Notifications\Concerns\SendsEmail;

    public function __construct(public Referral $referral) {}

    /** The unit handling this referral, as it is written in the email. */
    private function unit(): string
    {
        return $this->referral->referral_type === 'disciplinary'
            ? 'Student Discipline Unit (SDU)'
            : 'Guidance and Counseling Unit (GCU)';
    }

    /** Hides all but the last two characters, e.g. 2112345 -> *****45. */
    public static function maskId(?string $id): string
    {
        $id = (string) $id;
        $len = mb_strlen($id);
        if ($len <= 2) {
            return $id;
        }
        return str_repeat('*', $len - 2) . mb_substr($id, -2);
    }

    public function emailSubject(object $notifiable): string
    {
        return 'You have been referred to the ' . ($this->referral->referral_type === 'disciplinary' ? 'SDU' : 'GCU');
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = trim(($notifiable->first_name ?? '') . ' ' . ($notifiable->last_name ?? ''));

        $mail = (new MailMessage)
            ->subject($this->emailSubject($notifiable))
            ->greeting('Hello, ' . ($name !== '' ? $name : 'Student') . '.')
            ->line("You have been referred by {$this->referral->referrer_name} to the {$this->unit()}.");

        // The temporary password is only included while the student still has
        // not set their own. It is read now, never stored in the notification.
        $password = ($notifiable->must_change_password && $notifiable->temp_password)
            ? $notifiable->temp_password
            : null;

        if ($password) {
            $mail->line('Please log in to iCARE using your Student ID Number and the password given below:')
                ->line('**Student ID Number:** ' . self::maskId($notifiable->student_id))
                ->line('**Password:** ' . $password)
                ->line('Make sure to change your password upon logging in, and kindly update the details under your My Account.');
        } else {
            $mail->line('Please log in to iCARE using your Student ID Number and your password, and kindly update the details under your My Account.');
        }

        return $mail->salutation(new HtmlString('Much appreciated,<br>iCARE'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'    => 'referred_student',
            'message' => "You have been referred by {$this->referral->referrer_name} to the {$this->unit()}. Please go to My Account and update your details.",
        ];
    }
}