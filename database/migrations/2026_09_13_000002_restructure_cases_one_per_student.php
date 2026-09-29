<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Referrals can belong to a case (many referrals -> one case).
        //    The column already exists on this database (added by
        //    2026_09_11_140000_link_referrals_to_cases_per_student); this is
        //    just a safety net for a fresh install running migrations in order.
        if (!Schema::hasColumn('referrals', 'case_id')) {
            Schema::table('referrals', function (Blueprint $table) {
                $table->foreignId('case_id')->nullable()->after('student_id')->constrained('cases')->nullOnDelete();
            });
        }

        // 2. Merge duplicate case rows per student into a single canonical case,
        //    preserving every referral link and every child record (session notes,
        //    appointments, testing records, handoffs, documents) with zero data loss.
        //    Only needed once, while cases.referral_id still identifies each
        //    case's founding referral; safe to skip on a re-run.
        $studentIds = Schema::hasColumn('cases', 'referral_id')
            ? DB::table('cases')->select('student_id')->groupBy('student_id')->pluck('student_id')
            : collect();

        foreach ($studentIds as $studentId) {
            $cases = DB::table('cases')->where('student_id', $studentId)->orderBy('id')->get();
            $canonical = $cases->first();
            $duplicates = $cases->slice(1);

            // The canonical case's own founding referral gets linked directly.
            if ($canonical->referral_id) {
                DB::table('referrals')->where('id', $canonical->referral_id)->update(['case_id' => $canonical->id]);
            }

            foreach ($duplicates as $dup) {
                // Re-point every referral that belonged to this duplicate case.
                if ($dup->referral_id) {
                    DB::table('referrals')->where('id', $dup->referral_id)->update(['case_id' => $canonical->id]);
                }

                // Move any child records onto the canonical case.
                DB::table('session_notes')->where('case_id', $dup->id)->update(['case_id' => $canonical->id]);
                DB::table('appointments')->where('case_id', $dup->id)->update(['case_id' => $canonical->id]);
                DB::table('testing_records')->where('case_id', $dup->id)->update(['case_id' => $canonical->id]);
                DB::table('case_handoffs')->where('case_id', $dup->id)->update(['case_id' => $canonical->id]);
                DB::table('documents')
                    ->where('documentable_type', 'App\\Models\\CaseFile')
                    ->where('documentable_id', $dup->id)
                    ->update(['documentable_id' => $canonical->id]);

                // Preserve the duplicate case's narrative content on the canonical
                // case's background_info so nothing written by staff is lost.
                $preserved = trim(implode("\n", array_filter([
                    $dup->presenting_concern ? "Concern: {$dup->presenting_concern}" : null,
                    $dup->background_info ? "Background: {$dup->background_info}" : null,
                    $dup->interventions_applied ? "Interventions: {$dup->interventions_applied}" : null,
                    $dup->outcomes ? "Outcomes: {$dup->outcomes}" : null,
                    $dup->recommendations ? "Recommendations: {$dup->recommendations}" : null,
                    $dup->closure_summary ? "Closure summary: {$dup->closure_summary}" : null,
                ])));

                if ($preserved !== '') {
                    $note = "--- Merged from {$dup->case_number} (opened {$dup->opened_date}) ---\n{$preserved}";
                    $existingBg = DB::table('cases')->where('id', $canonical->id)->value('background_info');
                    $newBg = trim(($existingBg ? $existingBg . "\n\n" : '') . $note);
                    DB::table('cases')->where('id', $canonical->id)->update(['background_info' => $newBg]);
                }

                // Merge total_sessions and keep the case open if any duplicate was still active.
                $canonicalRow = DB::table('cases')->where('id', $canonical->id)->first();
                DB::table('cases')->where('id', $canonical->id)->update([
                    'total_sessions' => $canonicalRow->total_sessions + $dup->total_sessions,
                ]);
                if (!in_array($dup->status, ['resolved', 'closed']) && in_array($canonicalRow->status, ['resolved', 'closed'])) {
                    DB::table('cases')->where('id', $canonical->id)->update(['status' => 'open', 'closed_date' => null]);
                }
            }

            if ($duplicates->isNotEmpty()) {
                DB::table('cases')->whereIn('id', $duplicates->pluck('id'))->delete();
            }
        }

        // 3. The 1:1 referral_id column is now redundant - the relationship lives
        //    on referrals.case_id instead (one case has many referrals).
        //
        //    SQLite refuses to DROP COLUMN on a column that carries a
        //    REFERENCES clause (the FK is baked into the table definition
        //    there, not a separately droppable named constraint like
        //    MySQL's), so this cleanup is MySQL-only there. But the column
        //    was originally NOT NULL (back when every case had exactly one
        //    founding referral), and nothing sets it going forward, so on
        //    SQLite (used for the RBAC test suite) it at least needs to
        //    become nullable, or every case insert fails outright.
        if (DB::connection()->getDriverName() === 'mysql') {
            if (Schema::hasColumn('cases', 'referral_id')) {
                Schema::table('cases', function (Blueprint $table) {
                    $table->dropForeign(['referral_id']);
                    $table->dropColumn('referral_id');
                });
            }
        } else {
            $this->makeSqliteColumnNullable('cases', 'referral_id');
        }

        // 4. Restore the data-integrity guarantee: exactly one case per student.
        //    Add the unique index before dropping the old plain index - MySQL
        //    requires the student_id foreign key stay backed by an index at
        //    all times, so both must briefly coexist.
        // SHOW INDEX is MySQL-only syntax. On SQLite (used for the RBAC test
        // suite), check sqlite_master for the same named index instead.
        $isMysql = DB::connection()->getDriverName() === 'mysql';

        $hasUnique = $isMysql
            ? collect(DB::select("SHOW INDEX FROM cases WHERE Key_name = 'cases_student_id_unique'"))->isNotEmpty()
            : collect(DB::select(
                "SELECT name FROM sqlite_master WHERE type = 'index' AND tbl_name = 'cases' AND name = 'cases_student_id_unique'"
            ))->isNotEmpty();
        if (!$hasUnique) {
            Schema::table('cases', function (Blueprint $table) {
                $table->unique('student_id');
            });
        }

        $hasPlainIndex = $isMysql
            ? collect(DB::select("SHOW INDEX FROM cases WHERE Key_name = 'cases_student_id_index'"))->isNotEmpty()
            : collect(DB::select(
                "SELECT name FROM sqlite_master WHERE type = 'index' AND tbl_name = 'cases' AND name = 'cases_student_id_index'"
            ))->isNotEmpty();
        if ($hasPlainIndex) {
            Schema::table('cases', function (Blueprint $table) {
                $table->dropIndex(['student_id']);
            });
        }
    }

    public function down(): void
    {
        try {
            Schema::table('cases', function (Blueprint $table) {
                $table->dropUnique(['student_id']);
            });
        } catch (\Throwable $e) {
            // Same SQLite naming quirk as the up() dropUnique calls above -
            // safe to ignore if there's no index under that exact name.
        }

        Schema::table('cases', function (Blueprint $table) {
            $table->foreignId('referral_id')->nullable()->constrained('referrals')->restrictOnDelete();
        });

        Schema::table('referrals', function (Blueprint $table) {
            if (DB::connection()->getDriverName() === 'mysql') { $table->dropForeign(['case_id']); }
            $table->dropColumn('case_id');
        });
    }

    /**
     * Make a column nullable on SQLite by rebuilding the table.
     *
     * SQLite has no ALTER COLUMN, and Doctrine/DBAL's own rebuild-based
     * change() doesn't reliably regenerate CHECK/NOT NULL clauses baked
     * into the original CREATE TABLE text - it can copy them over
     * verbatim. So this reads the table's real CREATE TABLE SQL and
     * patches just the "<column> ... not null" fragment for the given
     * column, leaving every other column, CHECK constraint, and the
     * table's foreign key clauses untouched.
     *
     * As with rebuildSqliteRoleCheck() in
     * 2026_09_23_000005_add_system_admin_to_users_role_enum.php, the
     * replacement table is built under a brand-new temporary name FIRST
     * rather than renaming the original table away. Renaming the
     * original triggers SQLite's automatic rewrite of every OTHER
     * table's foreign-key reference text to point at the new name -
     * which would permanently break those references once the
     * temporary table is later dropped. Building under a fresh name
     * that nothing references yet avoids the rewrite entirely; only the
     * final drop+rename (of a table nothing points to during the swap
     * except by name) is needed.
     */
    private function makeSqliteColumnNullable(string $table, string $column): void
    {
        $createSql = DB::selectOne(
            "SELECT sql FROM sqlite_master WHERE type = 'table' AND name = ?",
            [$table]
        )->sql;

        $tempTable = $table . '_nullable_rebuild_new';

        // Match `"column" <type...> not null` up to the next column/constraint
        // boundary (a comma at the same nesting level or the closing paren),
        // and drop just the "not null" piece. The column definition itself
        // (type, default, etc.) is preserved verbatim; separate clauses
        // elsewhere in the CREATE TABLE text (e.g. a `foreign key(...)
        // references ...` line for the same column) are untouched since
        // they don't contain the literal "not null" substring being matched
        // here.
        $pattern = '/("' . preg_quote($column, '/') . '"\s+[a-z]+)\s+not null/i';
        $newCreateSql = preg_replace($pattern, '$1', $createSql, 1);

        $newCreateSql = preg_replace(
            '/create table\s+"?' . preg_quote($table, '/') . '"?/i',
            'CREATE TABLE ' . $tempTable,
            $newCreateSql,
            1
        );

        DB::statement($newCreateSql);
        DB::statement("INSERT INTO {$tempTable} SELECT * FROM {$table}");

        // FK enforcement has to be off only for this DROP - the original
        // table briefly doesn't exist while every other table's FK text
        // still names it, but the very next statement recreates it under
        // the same name with the same data, so nothing is left dangling
        // once this method returns.
        DB::statement('PRAGMA foreign_keys = OFF');
        DB::statement("DROP TABLE {$table}");
        DB::statement("ALTER TABLE {$tempTable} RENAME TO {$table}");
        DB::statement('PRAGMA foreign_keys = ON');
    }
};