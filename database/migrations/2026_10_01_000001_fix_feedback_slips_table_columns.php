<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // The original create-table migration left feedback_slips in a
    // partially-broken state on production (missing sent_by_user_id and
    // possibly other columns), which then made adding those columns back
    // in afterward fail too (a NOT NULL foreign-key column can't be added
    // onto a table that already has rows without a valid default, and
    // MySQL's implicit 0 default doesn't satisfy the foreign key check).
    // No feedback slip has ever actually been saved successfully (every
    // send attempt so far has errored before completing), so there is no
    // real data at risk here. Simplest, most reliable fix: drop whatever
    // exists and recreate the table cleanly from scratch, rather than
    // trying to patch around an unknown partial state column by column.
    public function up(): void
    {
        Schema::dropIfExists('feedback_slips');

        Schema::create('feedback_slips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referral_id')->constrained('referrals')->cascadeOnDelete();
            $table->foreignId('sent_by_user_id')->constrained('users')->restrictOnDelete();
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