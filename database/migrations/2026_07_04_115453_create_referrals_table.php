<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->string('referral_code')->unique();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('referred_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('referrer_name');
            $table->string('referrer_role');
            $table->string('referrer_college')->nullable();
            $table->enum('referral_type', [
                'counseling',
                'academic_coaching',
                'admission_slip',
                'psychological_testing',
                'disciplinary',
                'consultation',
            ]);
            $table->text('nature_of_concern');
            $table->enum('urgency_level', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->boolean('is_self_referred')->default(false);
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->enum('status', [
                'submitted',
                'acknowledged',
                'in_review',
                'scheduled',
                'in_progress',
                'referred_tmdu',
                'referred_external',
                'completed',
                'closed',
            ])->default('submitted');
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('acknowledged_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('violation_type')->nullable();
            $table->text('incident_description')->nullable();
            $table->date('incident_date')->nullable();
            $table->boolean('has_attachments')->default(false);
            $table->text('intake_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referrals');
    }
};