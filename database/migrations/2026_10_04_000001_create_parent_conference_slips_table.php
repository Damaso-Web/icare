<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // One immutable record per Parent Conference Slip issued for a case -
    // same "never overwritten" pattern as feedback_slips/session_notes. The
    // slip itself is handed to the parent face-to-face (it's not emailed or
    // routed anywhere in-system), so this is just the issuance record GCU
    // keeps: when it was issued, for what reason, and any remarks.
    public function up(): void
    {
        Schema::create('parent_conference_slips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->foreignId('issued_by_user_id')->constrained('users')->restrictOnDelete();
            $table->date('conference_date');
            $table->time('conference_time');
            $table->string('reason');
            $table->text('remarks')->nullable();
            $table->timestamp('issued_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parent_conference_slips');
    }
};