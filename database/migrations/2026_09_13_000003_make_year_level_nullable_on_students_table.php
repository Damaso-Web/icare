<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// The API validates year_level as nullable, but the column itself had no
// default and rejected NULL - any request that omitted it (e.g. adding a
// student before their year level is known) crashed with a raw SQL error
// instead of the intended "field is optional" behavior.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE students MODIFY year_level VARCHAR(255) NULL');
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE students MODIFY year_level VARCHAR(255) NOT NULL DEFAULT '1st Year'");
    }
};
