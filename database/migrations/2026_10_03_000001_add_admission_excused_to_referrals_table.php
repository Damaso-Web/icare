<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The Admission Slip (QF-OSS-GCU-09) excused/unexcused determination -
        // previously this lived on CaseIntervention.excused (an entry under
        // the generic Previous Interventions log), conflating a narrative log
        // with an official slip that has its own document code. Nullable:
        // null means "not yet decided", true/false is the actual
        // determination once staff saves the slip. Once it's explicitly
        // false, ReferralController::saveAdmissionSlip() locks the slip from
        // further edits - same "no take-backs" rule the old
        // CaseInterventionController::store() lock enforced.
        Schema::table('referrals', function (Blueprint $table) {
            $table->boolean('admission_excused')->nullable()->after('admission_time_out');
        });
    }

    public function down(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            $table->dropColumn('admission_excused');
        });
    }
};