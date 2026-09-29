<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('session_notes', function (Blueprint $table) {
            $table->foreignId('referral_id')->nullable()->after('case_id')->constrained('referrals')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('session_notes', function (Blueprint $table) {
            if (DB::connection()->getDriverName() === 'mysql') { $table->dropForeign(['referral_id']); }
            $table->dropColumn('referral_id');
        });
    }
};
