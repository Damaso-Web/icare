<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Without this, a handoff/endorsement was only ever tied to the case,
    // so a case carrying more than one referral (e.g. a GCU referral and
    // the sibling psychological_testing referral created by "Refer to
    // TMDU") showed the SAME handoff history on every one of its
    // referrals' SIF pages, even when the endorsement only really applied
    // to one of them. Nullable, since existing rows predate this column
    // and can't be retroactively attributed to a specific referral.
    public function up(): void
    {
        Schema::table('case_handoffs', function (Blueprint $table) {
            $table->foreignId('referral_id')->nullable()->after('case_id')
                ->constrained('referrals')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('case_handoffs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('referral_id');
        });
    }
};