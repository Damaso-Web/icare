<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cases', function (Blueprint $table) {
            $table->id();
            $table->string('case_number')->unique();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('referral_id')->constrained('referrals')->restrictOnDelete();
            $table->foreignId('primary_counselor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('current_unit', ['GCU', 'SDU', 'TMDU'])->default('GCU');
            $table->enum('case_type', [
                'counseling',
                'academic_coaching',
                'admission_slip',
                'psychological_testing',
                'disciplinary',
                'consultation',
            ]);
            $table->enum('status', [
                'open',
                'in_progress',
                'awaiting_testing',
                'awaiting_external',
                'on_hold',
                'resolved',
                'closed',
            ])->default('open');
            $table->date('opened_date');
            $table->date('closed_date')->nullable();
            $table->date('target_resolution_date')->nullable();
            $table->integer('total_sessions')->default(0);
            $table->timestamp('last_session_at')->nullable();
            $table->text('presenting_concern')->nullable();
            $table->text('background_info')->nullable();
            $table->text('interventions_applied')->nullable();
            $table->text('outcomes')->nullable();
            $table->text('recommendations')->nullable();
            $table->text('closure_summary')->nullable();
            $table->boolean('is_recurring')->default(false);
            $table->boolean('requires_follow_up')->default(false);
            $table->boolean('referred_to_tmdu')->default(false);
            $table->boolean('referred_externally')->default(false);
            $table->string('external_referral_destination')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cases');
    }
};