<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Adds the student-entered Family Information, Siblings Information,
    // and Educational Attainment fields used by the student portal's "My
    // Account" page and surfaced read-only on the SIF's Profile modal.
    // Father/mother names are split into first/middle/last, same as the
    // existing guardian_first_name/guardian_middle_name/guardian_last_name
    // columns (and the student's own name columns), rather than one plain
    // "name" string. Siblings are stored as JSON since a student can have
    // any number of them (repeatable rows on the frontend); each entry is
    // {first_name, middle_name, last_name, age, occupation} for the same
    // reason. Named distinctly from the existing guardian_* columns (which
    // capture the student's official guardian of record) and from the
    // existing "college"/"program" columns (which capture the student's
    // CURRENT enrollment, not school history).
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            // Family Information
            $table->string('father_first_name')->nullable()->after('guardian_relationship');
            $table->string('father_middle_name')->nullable()->after('father_first_name');
            $table->string('father_last_name')->nullable()->after('father_middle_name');
            $table->string('father_occupation')->nullable()->after('father_last_name');
            $table->string('father_contact_number')->nullable()->after('father_occupation');
            $table->string('mother_first_name')->nullable()->after('father_contact_number');
            $table->string('mother_middle_name')->nullable()->after('mother_first_name');
            $table->string('mother_last_name')->nullable()->after('mother_middle_name');
            $table->string('mother_occupation')->nullable()->after('mother_last_name');
            $table->string('mother_contact_number')->nullable()->after('mother_occupation');

            // Siblings Information - repeatable list, e.g.
            // [{"first_name": "...", "middle_name": "...", "last_name": "...", "age": "...", "occupation": "..."}]
            $table->json('siblings')->nullable()->after('mother_contact_number');

            // Educational Attainment (school history)
            $table->string('elementary_school')->nullable()->after('siblings');
            $table->string('elementary_year_graduated')->nullable()->after('elementary_school');
            $table->string('high_school')->nullable()->after('elementary_year_graduated');
            $table->string('high_school_year_graduated')->nullable()->after('high_school');
            $table->string('college_school')->nullable()->after('high_school_year_graduated');
            $table->string('college_year_graduated')->nullable()->after('college_school');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn([
                'father_first_name', 'father_middle_name', 'father_last_name', 'father_occupation', 'father_contact_number',
                'mother_first_name', 'mother_middle_name', 'mother_last_name', 'mother_occupation', 'mother_contact_number',
                'siblings',
                'elementary_school', 'elementary_year_graduated',
                'high_school', 'high_school_year_graduated',
                'college_school', 'college_year_graduated',
            ]);
        });
    }
};