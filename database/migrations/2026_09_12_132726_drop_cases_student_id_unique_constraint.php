<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Reconstructed to match this project's live database history. This duplicated
// the effect of 2026_09_12_065549_remove_unique_constraint_from_cases_student_id
// and is guarded so it's a no-op if that unique index is already gone.
return new class extends Migration
{
    public function up(): void
    {
        // SHOW INDEX is MySQL-only syntax. On SQLite (used for the RBAC test
        // suite), check sqlite_master for the same named index instead - the
        // original unique constraint there is almost always an implicit
        // "sqlite_autoindex_*" from being declared inline on table creation,
        // so this correctly comes back empty and the migration is a no-op.
        $hasUnique = DB::connection()->getDriverName() === 'mysql'
            ? collect(DB::select("SHOW INDEX FROM cases WHERE Key_name = 'cases_student_id_unique'"))->isNotEmpty()
            : collect(DB::select(
                "SELECT name FROM sqlite_master WHERE type = 'index' AND tbl_name = 'cases' AND name = 'cases_student_id_unique'"
            ))->isNotEmpty();

        if ($hasUnique) {
            Schema::table('cases', function ($table) {
                $table->dropUnique('cases_student_id_unique');
            });
        }
    }

    public function down(): void
    {
        // Intentionally left blank - uniqueness is restored by
        // 2026_09_13_000001_restructure_cases_one_per_student instead.
    }
};