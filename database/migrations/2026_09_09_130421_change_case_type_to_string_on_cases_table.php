<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Raw MySQL syntax - no-op on SQLite (used for the RBAC test suite),
        // which has no MODIFY COLUMN and stores this loosely-typed anyway.
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE cases MODIFY case_type VARCHAR(50) NOT NULL");
    }

    public function down(): void
    {
        // Not reversible safely without knowing prior enum values
    }
};