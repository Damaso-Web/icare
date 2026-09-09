<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE referrals MODIFY referral_type VARCHAR(50) NOT NULL");
        DB::statement("ALTER TABLE referrals MODIFY referrer_source VARCHAR(50) NULL");
    }

    public function down(): void
    {
        // Not reversible safely without knowing prior enum values
    }
};