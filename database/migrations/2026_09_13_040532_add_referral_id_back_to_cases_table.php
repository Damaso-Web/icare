<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // On SQLite (used for the RBAC test suite), the later migration
        // that used to drop this column (2026_09_13_000002) is now
        // MySQL-only - SQLite can't drop a column that carries a
        // REFERENCES clause - so the column never goes away there and is
        // already present by the time this migration runs. Guard with
        // hasColumn so this stays a safe no-op in that case.
        if (!Schema::hasColumn('cases', 'referral_id')) {
            Schema::table('cases', function (Blueprint $table) {
                $table->foreignId('referral_id')->nullable()->after('student_id')->constrained('referrals')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            Schema::table('cases', function (Blueprint $table) {
                $table->dropForeign(['referral_id']);
                $table->dropColumn('referral_id');
            });
        }
    }
};