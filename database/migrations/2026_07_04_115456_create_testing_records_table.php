<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('testing_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('cases')->restrictOnDelete();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('referred_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_tester_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', [
                'pending',
                'scheduled',
                'in_progress',
                'completed',
                'report_sent',
            ])->default('pending');
            $table->json('tests_administered')->nullable();
            $table->date('testing_date')->nullable();
            $table->date('report_date')->nullable();
            $table->text('assessment_summary')->nullable();
            $table->text('findings')->nullable();
            $table->text('recommendations')->nullable();
            $table->boolean('report_sent_to_gcu')->default(false);
            $table->timestamp('report_sent_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('testing_records');
    }
};