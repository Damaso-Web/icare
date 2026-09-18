<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Same issue as year_level (see 2026_09_13_000003): the API validates
// college as nullable but the column itself had no default and rejected
// NULL, crashing "add student" with a raw SQL error whenever it was omitted.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE students MODIFY college VARCHAR(255) NULL');
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE students MODIFY college VARCHAR(255) NOT NULL DEFAULT ''");
    }
};
