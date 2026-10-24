<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * testing_records.status started life as a 5-value enum and every status
 * the app actually writes today (fee_form_pending, or_submitted,
 * test_administered, awaiting_results, par_scheduled, test_results_issued)
 * was added later via raw "ALTER TABLE ... MODIFY ENUM(...)" statements,
 * which are MySQL-only syntax. Those migrations assumed SQLite (used by
 * the test suite) "stores status loosely-typed anyway" and skipped
 * themselves there - that's wrong: Laravel's SQLite grammar backs
 * ->enum() with a real CHECK (status IN (...)) constraint, so SQLite kept
 * enforcing the ORIGINAL 5-value list the whole time. Any test creating or
 * updating a TestingRecord to one of the newer statuses (e.g.
 * 'test_administered') has been failing with "CHECK constraint failed:
 * status" since that first rename migration - which is exactly why no test
 * ever caught the Dashboard counting the wrong status names for TMDU's "In
 * Progress"/"Completed" cards (see DashboardController::tmduDashboard()).
 *
 * Converting the column to a plain string removes the stale constraint on
 * every driver and matches what those migrations' comments already assumed
 * was true, so no further "MODIFY ENUM" migration is ever needed again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('testing_records', function (Blueprint $table) {
            $table->string('status', 30)->default('pending')->change();
        });
    }

    public function down(): void
    {
        // Not reversed - going back to an enum/CHECK list would risk
        // rejecting real rows already written under the newer status names.
    }
};