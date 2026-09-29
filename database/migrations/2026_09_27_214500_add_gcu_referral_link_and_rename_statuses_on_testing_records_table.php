<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // New columns:
        // - referral_id: links this TestingRecord to the new, shared
        //   Referral row created when GCU refers a case to TMDU (so the
        //   referral shows up in the Referral Queue / SIF for both units).
        // - reason: the reason GCU typed when referring - previously only
        //   stored on the CaseHandoff row, not on the TestingRecord itself.
        // - or_stamped_at / or_stamped_by_user_id: face-to-face confirmation
        //   that TMDU physically received and stamped the student's OR,
        //   captured at the same in-person visit as scheduling the test.
        Schema::table('testing_records', function (Blueprint $table) {
            $table->foreignId('referral_id')->nullable()->after('case_id')
                ->constrained('referrals')->nullOnDelete();
            $table->text('reason')->nullable()->after('referred_by_user_id');
            $table->timestamp('or_stamped_at')->nullable()->after('or_uploaded_at');
            $table->foreignId('or_stamped_by_user_id')->nullable()->after('or_stamped_at')
                ->constrained('users')->nullOnDelete();
        });

        // The original migration's enum never actually included
        // 'fee_form_pending', 'or_submitted', or 'par_scheduled', even
        // though the controller already writes those values - so those
        // writes have been failing/truncating against the DB constraint.
        // Fix that here, and rename the two stages the team renamed
        // ('completed' -> 'test_administered', 'report_sent' ->
        // 'test_results_issued'). Widen first (superset of old + new) so
        // existing rows are never briefly invalid, migrate the data, then
        // narrow to the final list.
        //
        // The raw ALTER statements are MySQL-only enum syntax - no-op on
        // SQLite (used for the RBAC test suite), which has no MODIFY COLUMN
        // and stores status loosely-typed anyway, so every value here is
        // already accepted there. The data-fix updates below still run on
        // every driver since they're harmless no-ops when no rows match.
        $isMysql = DB::connection()->getDriverName() === 'mysql';

        if ($isMysql) {
            DB::statement("ALTER TABLE testing_records MODIFY status ENUM(
                'pending','fee_form_pending','or_submitted','scheduled','in_progress',
                'completed','test_administered','par_scheduled','report_sent','test_results_issued'
            ) NOT NULL DEFAULT 'pending'");
        }

        DB::table('testing_records')->where('status', 'completed')->update(['status' => 'test_administered']);
        DB::table('testing_records')->where('status', 'report_sent')->update(['status' => 'test_results_issued']);

        if ($isMysql) {
            DB::statement("ALTER TABLE testing_records MODIFY status ENUM(
                'pending','fee_form_pending','or_submitted','scheduled','in_progress',
                'test_administered','par_scheduled','test_results_issued'
            ) NOT NULL DEFAULT 'pending'");
        }
    }

    public function down(): void
    {
        $isMysql = DB::connection()->getDriverName() === 'mysql';

        if ($isMysql) {
            DB::statement("ALTER TABLE testing_records MODIFY status ENUM(
                'pending','fee_form_pending','or_submitted','scheduled','in_progress',
                'completed','test_administered','par_scheduled','report_sent','test_results_issued'
            ) NOT NULL DEFAULT 'pending'");
        }

        DB::table('testing_records')->where('status', 'test_administered')->update(['status' => 'completed']);
        DB::table('testing_records')->where('status', 'test_results_issued')->update(['status' => 'report_sent']);

        if ($isMysql) {
            DB::statement("ALTER TABLE testing_records MODIFY status ENUM(
                'pending','scheduled','in_progress','completed','report_sent'
            ) NOT NULL DEFAULT 'pending'");
        }

        Schema::table('testing_records', function (Blueprint $table) use ($isMysql) {
            // SQLite can't drop a foreign key constraint without recreating
            // the table, and doesn't need to anyway - dropping the columns
            // below removes the constraint along with them there. Only do
            // the explicit drop on MySQL, where the FK exists independently
            // of the column and must be removed first.
            if ($isMysql) {
                $table->dropForeign(['referral_id']);
                $table->dropForeign(['or_stamped_by_user_id']);
            }
            $table->dropColumn(['referral_id', 'reason', 'or_stamped_at', 'or_stamped_by_user_id']);
        });
    }
};