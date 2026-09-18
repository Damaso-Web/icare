<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

// Reconstructed to match this project's live database history - the pivot
// approach from 2026_09_11_083505_create_student_cases_table was abandoned in
// favor of referrals.case_id (see 2026_09_11_140000_link_referrals_to_cases_per_student).
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('student_cases');
    }

    public function down(): void
    {
        Schema::create('student_cases', function ($table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->timestamps();
        });
    }
};
