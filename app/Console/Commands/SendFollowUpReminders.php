<?php

namespace App\Console\Commands;

use App\Models\CaseFile;
use App\Notifications\FollowUpReminderNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class SendFollowUpReminders extends Command
{
    protected $signature = 'reminders:follow-up';

    protected $description = 'Notify each case\'s counselor about cases still flagged as requiring follow-up.';

    public function handle(): int
    {
        $cases = CaseFile::with('counselor')
            ->where('requires_follow_up', true)
            ->whereNotNull('primary_counselor_id')
            ->get();

        foreach ($cases as $case) {
            if ($case->counselor) {
                Notification::send($case->counselor, new FollowUpReminderNotification($case));
            }
        }

        $this->info("Sent {$cases->count()} follow-up reminder(s).");

        return self::SUCCESS;
    }
}
