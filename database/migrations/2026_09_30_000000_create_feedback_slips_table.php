<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Each row is one sent copy of the Feedback Slip (QF-OSS-03) - a
        // permanent, append-only log, the same pattern as session_notes.
        // Previously "sending" a feedback slip just overwrote a handful of
        // columns on the referrals table, so the form stayed open/editable
        // afterward and there was no record of what was actually sent
        // before the next edit. Now every "Send to Referrer" click inserts
        // a brand new row here; nothing in this table is ever updated.
        Schema::create('feedback_slips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referral_id')->constrained('referrals')->cascadeOnDelete();
            $table->foreignId('case_id')->nullable()->constrained('cases')->nullOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();

            // Document Ctrl No. as printed on this particular copy.
            $table->string('ctrl_no')->nullable();
            // "Intervention/s or Assistance Provided" checklist, same keys as
            // the old referrals.feedback_checklist column.
            $table->json('checklist')->nullable();
            $table->string('referred_other_text')->nullable();
            $table->string('others_text')->nullable();
            $table->text('notes');

            // Attending OSS Personnel - whoever filled out and sent this copy.
            $table->foreignId('recorded_by_user_id')->constrained('users')->restrictOnDelete();

            // Who this copy was sent to (the referrer). A user account when
            // the referrer has one (faculty/dean's secretary/etc.); the
            // referrer_name/referrer_role snapshot otherwise (e.g. a walk-in
            // or self-referral with no system account).
            $table->foreignId('sent_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('sent_to_name')->nullable();
            $table->string('sent_to_role')->nullable();

            $table->timestamp('sent_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_slips');
    }
};