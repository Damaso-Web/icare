<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            // Feedback slip - a copy of the counselor's summary sent back to the
            // referrer for transparency / progress tracking.
            $table->text('feedback_notes')->nullable();
            $table->timestamp('feedback_sent_at')->nullable();
            $table->foreignId('feedback_sent_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            // Admission slip - only applicable to class_attendance referrals.
            $table->date('admission_date')->nullable();
            $table->time('admission_time_in')->nullable();
            $table->time('admission_time_out')->nullable();
            $table->text('admission_remarks')->nullable();
            $table->timestamp('admission_issued_at')->nullable();
            $table->foreignId('admission_issued_by_user_id')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            $table->dropForeign(['feedback_sent_by_user_id']);
            $table->dropForeign(['admission_issued_by_user_id']);
            $table->dropColumn([
                'feedback_notes',
                'feedback_sent_at',
                'feedback_sent_by_user_id',
                'admission_date',
                'admission_time_in',
                'admission_time_out',
                'admission_remarks',
                'admission_issued_at',
                'admission_issued_by_user_id',
            ]);
        });
    }
};
