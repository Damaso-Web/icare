<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Slips issued before referral_id existed had no referral, and the page showed
// those on EVERY referral of the case. Attach each one to the referral that was
// the latest on its case when the slip was issued (else the case's oldest one).
return new class extends Migration
{
    public function up(): void
    {
        $slips = DB::table('parent_conference_slips')->whereNull('referral_id')->get();

        foreach ($slips as $slip) {
            $base = DB::table('referrals')
                ->where('case_id', $slip->case_id)
                ->whereNull('deleted_at')
                ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('testing_records')
                    ->whereColumn('testing_records.referral_id', 'referrals.id'));

            $issued = $slip->issued_at ?? $slip->created_at;

            $referralId = (clone $base)->where('created_at', '<=', $issued)->orderByDesc('created_at')->value('id')
                ?? (clone $base)->orderBy('created_at')->value('id');

            if ($referralId) {
                DB::table('parent_conference_slips')->where('id', $slip->id)->update(['referral_id' => $referralId]);
            }
        }
    }

    public function down(): void
    {
        // Not reversible - the original (null) link carried no information.
    }
};