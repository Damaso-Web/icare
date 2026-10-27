<?php

namespace App\Notifications\Concerns;

use App\Channels\LoggedMailChannel;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Queue\SerializesModels;

/**
 * Gives a notification an email copy of its in-app message (B-free FR-03, FR-10,
 * FR-11, FR-19). The email repeats the same message the bell shows, with a
 * subject, a greeting, and a button to open iCARE. Delivery goes through
 * LoggedMailChannel, which logs it and retries it if the mail server is down.
 */
trait SendsEmail
{
    use SerializesModels;

    public function via(object $notifiable): array
    {
        return ['database', LoggedMailChannel::class];
    }

    /** Override in a notification to change the subject line. */
    public function emailSubject(object $notifiable): string
    {
        $data = $this->toArray($notifiable);
        return match ($data['type'] ?? '') {
            'new_referral'              => 'New referral received',
            'new_complaint'             => 'New complaint filed',
            'referral_acknowledged'     => 'Your referral was received',
            'referral_assigned'         => 'A referral was assigned to you',
            'referral_status_updated'   => 'Referral status update',
            'referred_to_tmdu'          => 'You were referred for testing',
            'testing_referral'          => 'New testing referral',
            'testing_appointment_ready' => 'Your testing schedule will be arranged',
            'testing_scheduled'         => 'Your testing is scheduled',
            'par_scheduled'             => 'Your PAR release is scheduled',
            'par_on_hold'               => 'Your PAR release is on hold',
            'par_released'              => 'Your PAR results are ready',
            'fill_out_forms'            => 'Please fill out your forms in iCARE',
            'appointment_requested'     => 'Appointment request',
            'appointment_confirmed'     => 'Your appointment is confirmed',
            'appointment_reminder'      => 'Appointment reminder',
            'documents_required'        => 'Documents to bring to your appointment',
            default                     => 'iCARE notification',
        };
    }

    public function toMail(object $notifiable): MailMessage
    {
        $data  = $this->toArray($notifiable);
        $name  = $notifiable->first_name ?? $notifiable->name ?? null;
        $front = rtrim((string) config('app.frontend_url'), '/');

        return \App\Support\MailTracking::off((new MailMessage)
            ->subject($this->emailSubject($notifiable))
            ->greeting($name ? 'Hello ' . $name . ',' : 'Hello,')
            ->line((string) ($data['message'] ?? 'You have a new notification in iCARE.'))
            ->action('Open iCARE', $front ?: url('/'))
            ->line('This is an automatic message from iCARE. Please do not reply to this email.')
            ->salutation('iCARE - BSU Office of Student Services'));
    }
}