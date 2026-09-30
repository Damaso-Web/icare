<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Every Feedback Slip send now becomes its own immutable history row,
    // the same way session notes are never overwritten - instead of the
    // single set of feedback_* columns on referrals just getting replaced
    // on each send. The referrals.feedback_* columns are left in place
    // (still used as a quick "last sent" summary) so nothing else that
    // reads them needs to change.
    public function up(): void
    {
        Schema::create('feedback_slips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referral_id')->constrained('referrals')->cascadeOnDelete();
            // "Recorded By" - whoever actually sent this slip.
            $table->foreignId('sent_by_user_id')->constrained('users')->restrictOnDelete();
            // "Sent To" - snapshot of the referrer's name/role at send time,
            // so the history stays accurate even if that data changes later.
            $table->string('sent_to_name')->nullable();
            $table->string('sent_to_role')->nullable();
            $table->json('feedback_checklist')->nullable();
            $table->string('feedback_referred_other_text')->nullable();
            $table->string('feedback_others_text')->nullable();
            $table->text('feedback_notes');
            $table->string('feedback_ctrl_no')->nullable();
            $table->timestamp('sent_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_slips');
    }
};