<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Scopes "Refer to TMDU" per-referral instead of per-case: records
        // which referral a TMDU testing escalation was actually started
        // from, so a DIFFERENT referral under the same case (one student has
        // one case for life) can start its own, independent escalation
        // instead of always being told "Already referred to TMDU" just
        // because some other referral on the case has one in progress.
        // Nullable - records created before this column existed (and any
        // created without a referral_id on the request) won't have one, and
        // simply won't be matched by the new per-referral lookup.
        Schema::table('testing_records', function (Blueprint $table) {
            $table->foreignId('source_referral_id')->nullable()->after('referral_id')->constrained('referrals')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('testing_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_referral_id');
        });
    }
};