<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            // Feedback Slip (QF-OSS-03) "Intervention/s or Assistance Provided"
            // checklist. Stored as a JSON array of the selected keys: interview,
            // counseling, psychological_testing, referred_scholarship,
            // referred_other, others.
            $table->json('feedback_checklist')->nullable()->after('feedback_notes');
            // Free-text blanks for the two checklist rows that carry one
            // ("Referred to ____ for interventions" / "Others: ____").
            $table->string('feedback_referred_other_text')->nullable()->after('feedback_checklist');
            $table->string('feedback_others_text')->nullable()->after('feedback_referred_other_text');
            // Ctrl No. on the printed slip. Freely editable for now - restricting
            // this to a super_admin role is a follow-up once that role exists.
            $table->string('feedback_ctrl_no')->nullable()->after('feedback_others_text');
        });
    }

    public function down(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            $table->dropColumn([
                'feedback_checklist',
                'feedback_referred_other_text',
                'feedback_others_text',
                'feedback_ctrl_no',
            ]);
        });
    }
};