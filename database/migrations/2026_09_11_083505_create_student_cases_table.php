<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Reconstructed to match this project's live database history: an intermediate
// student<->case pivot table was tried here and later removed in
// 2026_09_13_000001_drop_student_cases_table. Kept as a no-op-safe stub so a
// fresh install replays the same migration history as the shared database.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('student_cases')) {
            return;
        }

        Schema::create('student_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_cases');
    }
};
