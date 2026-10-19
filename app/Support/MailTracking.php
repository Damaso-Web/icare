<?php

namespace App\Support;

use Illuminate\Notifications\Messages\MailMessage;
use Symfony\Component\Mime\Email;

/**
 * Turns off click/open tracking on a message so links in it (password reset,
 * account notices) go straight to iCARE instead of through a third-party
 * redirect address. The headers are understood by the common mail services.
 */
class MailTracking
{
    public static function off(MailMessage $mail): MailMessage
    {
        return $mail->withSymfonyMessage(function (Email $message) {
            $h = $message->getHeaders();
            $h->addTextHeader('X-SMTPAPI', '{"filters":{"clicktrack":{"settings":{"enable":0}},"opentrack":{"settings":{"enable":0}}}}'); // SendGrid
            $h->addTextHeader('X-Mailgun-Track-Clicks', 'no');
            $h->addTextHeader('X-Mailgun-Track-Opens', 'no');
            $h->addTextHeader('X-PM-TrackLinks', 'None');       // Postmark
            $h->addTextHeader('X-PM-TrackOpens', 'false');
            $h->addTextHeader('X-MC-Track', 'False, False');    // Mandrill
            $h->addTextHeader('X-Mailin-Track', '0');           // Brevo
            $h->addTextHeader('X-Auto-Response-Suppress', 'All');
        });
    }
}