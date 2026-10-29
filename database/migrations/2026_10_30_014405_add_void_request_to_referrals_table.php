<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Once a referral is acknowledged it's GCU/SDU/TMDU's record and can
        // no longer be deleted by the referrer outright (same rule as Edit
        // Referral) - instead they can ask for it to be voided. A non-null
        // void_requested_at means a request is pending; it and void_reason
        // are cleared back to null once GCU approves (archives it) or denies
        // the request, so this column doubles as "is a request pending" with
        // no separate status column needed.
        Schema::table('referrals', function (Blueprint $table) {
            $table->timestamp('void_requested_at')->nullable()->after('acknowledged_at');
            $table->text('void_reason')->nullable()->after('void_requested_at');
        });
    }

    public function down(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            $table->dropColumn(['void_requested_at', 'void_reason']);
        });
    }
};