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
        if (Schema::hasColumn('cases', 'referral_id')) {
            Schema::table('cases', function (Blueprint $table) {
                $table->dropForeign(['referral_id']);
                $table->dropColumn('referral_id');
            });
        }

        // 4. Restore the data-integrity guarantee: exactly one case per student.
        //    Add the unique index before dropping the old plain index - MySQL
        //    requires the student_id foreign key stay backed by an index at
        //    all times, so both must briefly coexist.
        $hasUnique = collect(DB::select("SHOW INDEX FROM cases WHERE Key_name = 'cases_student_id_unique'"))->isNotEmpty();
        if (!$hasUnique) {
            Schema::table('cases', function (Blueprint $table) {
                $table->unique('student_id');
            });
        }

        $hasPlainIndex = collect(DB::select("SHOW INDEX FROM cases WHERE Key_name = 'cases_student_id_index'"))->isNotEmpty();
        if ($hasPlainIndex) {
            Schema::table('cases', function (Blueprint $table) {
                $table->dropIndex(['student_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->dropUnique(['student_id']);
            $table->foreignId('referral_id')->nullable()->constrained('referrals')->restrictOnDelete();
        });

        Schema::table('referrals', function (Blueprint $table) {
            $table->dropForeign(['case_id']);
            $table->dropColumn('case_id');
        });
    }
};
