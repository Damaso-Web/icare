<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// B534: when a date is declared a holiday/suspension and appointments already
// exist on it, each affected appointment is flagged here (which holiday, and
// when) and, once handled, how it was resolved - auto-cancelled, auto-moved to
// the next open business day, or left for manual review. This is kept apart
// from the normal `status` column so the rest of the app's status-based
// filters and rules are unaffected; it is purely a "needs attention" flag.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->foreignId('holiday_conflict_entry_id')->nullable()->after('status')->constrained('calendar_entries')->nullOnDelete();
            $table->timestamp('holiday_conflict_flagged_at')->nullable()->after('holiday_conflict_entry_id');
            $table->string('holiday_conflict_resolution', 30)->nullable()->after('holiday_conflict_flagged_at');
            $table->timestamp('holiday_conflict_resolved_at')->nullable()->after('holiday_conflict_resolution');
            $table->foreignId('holiday_conflict_resolved_by_user_id')->nullable()->after('holiday_conflict_resolved_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn(['holiday_conflict_entry_id', 'holiday_conflict_flagged_at', 'holiday_conflict_resolution', 'holiday_conflict_resolved_at', 'holiday_conflict_resolved_by_user_id']);
        });
    }
};