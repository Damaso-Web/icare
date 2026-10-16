<?php

namespace App\Console\Commands;

use App\Channels\LoggedMailChannel;
use App\Models\EmailLog;
use Illuminate\Console\Command;

// Sends notification emails that failed again (FR-22). Runs every few minutes;
// each email gets up to LoggedMailChannel::MAX_ATTEMPTS tries, with a longer
// wait after every failure, and is then marked "gave_up".
class RetryFailedEmails extends Command
{
    protected $signature = 'emails:retry-failed';
    protected $description = 'Retry notification emails that could not be sent.';

    public function handle(LoggedMailChannel $channel): int
    {
        $sent = $failed = 0;

        EmailLog::where('status', 'failed')->whereNotNull('payload')->orderBy('id')->limit(100)->get()
            ->each(function (EmailLog $log) use ($channel, &$sent, &$failed) {
                // Wait 5 minutes after the first failure, 10 after the second.
                if ($log->last_attempt_at && $log->last_attempt_at->gt(now()->subMinutes(5 * $log->attempts))) {
                    return;
                }

                $notifiable = ($log->notifiable_type)::find($log->notifiable_id);
                $notification = @unserialize($log->payload);
                if (!$notifiable || !$notification) {
                    $log->update(['status' => 'gave_up', 'payload' => null, 'error_message' => 'Recipient or message no longer available.']);
                    return;
                }

                $channel->attempt($log, $notifiable, $notification) ? $sent++ : $failed++;
            });

        $this->info("Emails retried: {$sent} sent, {$failed} failed again.");
        return self::SUCCESS;
    }
}