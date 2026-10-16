<?php

namespace App\Channels;

use App\Models\EmailLog;
use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Notifications\Notification;

/**
 * Sends the notification's email like the normal mail channel, but:
 *  - writes every email to email_logs (sent or failed),
 *  - never lets a mail-server problem break the action that triggered it
 *    (a referral is still saved if the email cannot go out), and
 *  - keeps failed emails so `emails:retry-failed` can send them again (FR-22).
 */
class LoggedMailChannel
{
    public const MAX_ATTEMPTS = 3;

    public function send($notifiable, Notification $notification): void
    {
        $email = $notifiable->routeNotificationFor('mail', $notification);
        if (is_array($email)) {
            $email = array_key_first($email);
        }
        // No usable address (blank, or not an email at all): the in-app notice still reaches them.
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $log = EmailLog::create([
            'notifiable_type' => $notifiable::class,
            'notifiable_id'   => $notifiable->getKey(),
            'recipient_email' => $email,
            'notification'    => $notification::class,
            'subject'         => $this->subject($notifiable, $notification),
            'status'          => 'failed',
            'attempts'        => 0,
        ]);

        $this->attempt($log, $notifiable, $notification);
    }

    /** One delivery attempt; updates the log either way. Returns true when sent. */
    public function attempt(EmailLog $log, $notifiable, Notification $notification): bool
    {
        $log->attempts++;
        $log->last_attempt_at = now();

        try {
            app(MailChannel::class)->send($notifiable, $notification);
        } catch (\Throwable $e) {
            report($e);
            $log->status        = $log->attempts >= self::MAX_ATTEMPTS ? 'gave_up' : 'failed';
            $log->error_message = mb_substr($e->getMessage(), 0, 1000);
            $log->payload       = $log->status === 'failed' ? $this->pack($notification) : null;
            $log->save();
            return false;
        }

        $log->status        = 'sent';
        $log->sent_at       = now();
        $log->error_message = null;
        $log->payload       = null;
        $log->save();
        return true;
    }

    private function subject($notifiable, Notification $notification): ?string
    {
        try {
            return $notification->toMail($notifiable)->subject;
        } catch (\Throwable) {
            return null;
        }
    }

    private function pack(Notification $notification): ?string
    {
        try {
            return serialize($notification);
        } catch (\Throwable) {
            return null;
        }
    }
}