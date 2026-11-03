<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Referral;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

// B#, 11/4/2026: referral codes used to be generated from the Referral
// Slip's (QF-OSS-01) own Ctrl No. whenever one was configured in Management,
// which gave some referrals a "26-1-0069" code - the exact same shape as a
// Student Information File's own Ctrl No., and easy to mistake for one in a
// list like Sent Referrals. New referrals now always use the plain
// REF-{year}-#### scheme instead (see Referral::booted()); this command is a
// one-time fix to bring every OLD referral still on the "YY-T-####" scheme
// over to the new one too, so the whole list reads consistently.
class RenumberLegacyReferralCodes extends Command
{
    protected $signature = 'referrals:renumber-legacy-codes {--dry-run : List what would change without saving anything}';
    protected $description = 'Renumbers referral codes still on the old "26-1-####" scheme to the current REF-{year}-#### scheme';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        // Anything already on the new scheme is left exactly as it is -
        // this only touches codes that don't start with "REF-".
        $legacy = Referral::withTrashed()
            ->where('referral_code', 'not like', 'REF-%')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'referral_code', 'created_at']);

        if ($legacy->isEmpty()) {
            $this->info('Nothing to do - every referral is already on the REF-{year}-#### scheme.');
            return self::SUCCESS;
        }

        // Track the next free sequence number per year, seeded from
        // whatever REF-{year}-#### codes already exist for that year (some
        // referrals may already be on the new scheme), so a renumbered code
        // never collides with one already in use.
        $nextByYear = [];
        $plan = []; // [id, old, new]

        foreach ($legacy as $referral) {
            $year = $referral->created_at?->year ?? now()->year;

            if (!array_key_exists($year, $nextByYear)) {
                $max = Referral::withTrashed()
                    ->where('referral_code', 'like', "REF-{$year}-%")
                    ->pluck('referral_code')
                    ->map(fn ($code) => (int) substr($code, -4))
                    ->max();
                $nextByYear[$year] = ($max ?? 0) + 1;
            }

            $new = 'REF-' . $year . '-' . str_pad((string) $nextByYear[$year], 4, '0', STR_PAD_LEFT);
            $nextByYear[$year]++;

            $plan[] = ['id' => $referral->id, 'old' => $referral->referral_code, 'new' => $new];
        }

        $this->table(['Referral ID', 'Old Code', 'New Code'], $plan);

        if ($dryRun) {
            $this->comment(count($plan) . ' referral(s) would be renumbered. Re-run without --dry-run to apply.');
            return self::SUCCESS;
        }

        if (!$this->confirm(count($plan) . ' referral(s) will be renumbered. This updates the referral_code column directly and cannot be undone automatically - continue?')) {
            $this->comment('Cancelled - nothing was changed.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($plan) {
            foreach ($plan as $row) {
                // Plain query-builder update, not the Eloquent model: this
                // skips Referral::booted()'s creating hook (which only fires
                // on insert anyway) and leaves updated_at untouched, so the
                // record's own history stays accurate.
                DB::table('referrals')->where('id', $row['id'])->update(['referral_code' => $row['new']]);
            }

            AuditLog::record(
                'updated',
                'Renumbered ' . count($plan) . ' referral code(s) from the old "YY-T-####" scheme to REF-{year}-#### (one-time fix, referrals:renumber-legacy-codes).'
            );
        });

        $this->info(count($plan) . ' referral(s) renumbered.');
        $this->comment('Note: past audit log entries and notifications that already mention the old codes keep the old text - only the live referral_code field was changed.');

        return self::SUCCESS;
    }
}