<?php

namespace App\Console\Commands;

use App\Models\CaseFile;
use App\Notifications\FollowUpReminderNotification;
use Illuminate\Console\Command;

class SendFollowUpReminders extends Command
{
    protected $signature = 'reminders:follow-up';
    protected $description = 'Send reminder notifications for cases still flagged as requiring follow-up';

    public function handle()
    {
        $cases = CaseFile::where('requires_follow_up', true)
            ->where('follow_up_flagged_at', '<=', now()->subDays(3))
            ->with('counselor')
            ->get();

        $sent = 0;

        foreach ($cases as $case) {
            if ($case->counselor) {
                $case->counselor->notify(new FollowUpReminderNotification($case));
                $sent++;
            }
        }

        $this->info("Sent {$sent} follow-up reminder(s).");
    }
}