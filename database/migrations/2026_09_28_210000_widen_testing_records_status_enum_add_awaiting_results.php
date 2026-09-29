<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds 'awaiting_results' to the testing_records.status enum for the new
 * Testing Record Details page's 5-stage status bar (Pending | Scheduled for
 * Testing | Test Administered | Awaiting Results | Results Released).
 *
 * Saving "Psychological Tests Administered" now moves a record straight to
 * 'awaiting_results' (there's no separate resting state for
 * 'test_administered' alone going forward) - see
 * TestingRecordController::administerTests(). The old 'test_administered'
 * and 'par_scheduled' values are kept on the enum for existing/legacy rows
 * and both display under the "Awaiting Results" bar step.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Raw MySQL enum syntax - no-op on SQLite (used for the RBAC test
        // suite), which has no MODIFY COLUMN and stores status loosely-typed
        // anyway, so 'awaiting_results' is already accepted there.
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE testing_records MODIFY COLUMN status ENUM(
            'pending','fee_form_pending','or_submitted','scheduled','in_progress',
            'test_administered','awaiting_results','par_scheduled','test_results_issued'
        )");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE testing_records MODIFY COLUMN status ENUM(
            'pending','fee_form_pending','or_submitted','scheduled','in_progress',
            'test_administered','par_scheduled','test_results_issued'
        )");
    }
};