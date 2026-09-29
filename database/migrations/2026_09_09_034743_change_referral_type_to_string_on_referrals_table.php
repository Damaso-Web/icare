<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Raw MySQL syntax - SQLite (used for the RBAC test suite) has no
        // MODIFY COLUMN and stores these loosely-typed anyway, so this is a
        // safe no-op there.
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE referrals MODIFY referral_type VARCHAR(50) NOT NULL");
        DB::statement("ALTER TABLE referrals MODIFY referrer_source VARCHAR(50) NULL");
    }

    public function down(): void
    {
        // Not reversible safely without knowing prior enum values
    }
};