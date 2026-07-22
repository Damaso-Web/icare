<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('recorded_by_user_id')->constrained('users')->restrictOnDelete();
            $table->integer('session_number');
            $table->date('session_date');
            $table->time('session_start_time')->nullable();
            $table->time('session_end_time')->nullable();
            $table->integer('duration_minutes')->nullable();
            $table->enum('session_type', [
                'initial',
                'follow_up',
                'assessment',
                'conference',
                'final',
            ])->default('follow_up');
            $table->text('observations');
            $table->text('interventions')->nullable();
            $table->text('student_response')->nullable();
            $table->text('next_steps')->nullable();
            $table->boolean('student_showed_up')->default(true);
            $table->enum('mood_rating', ['1','2','3','4','5'])->nullable();
            $table->boolean('follow_up_needed')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_notes');
    }
};