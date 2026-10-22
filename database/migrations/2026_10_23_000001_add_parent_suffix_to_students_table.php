<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// B523: a suffix (Jr., Sr., II, ...) for the father and mother, next to their
// names in the Family section of the student's My Account page. Siblings
// already store a suffix inside the existing `siblings` JSON column, so no
// column is needed for them here.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('father_suffix', 20)->nullable()->after('father_last_name');
            $table->string('mother_suffix', 20)->nullable()->after('mother_last_name');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['father_suffix', 'mother_suffix']);
        });
    }
};