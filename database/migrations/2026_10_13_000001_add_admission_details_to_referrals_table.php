<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The rest of the paper Admission Slip (QF-OSS-GCU-09): number of
        // days absent/tardy, causes of absences, actions taken, and the
        // "official activity" / "others" write-ins. Kept together as one JSON
        // value next to the slip's existing date, time and excused columns.
        Schema::table('referrals', function (Blueprint $table) {
            $table->json('admission_details')->nullable()->after('admission_remarks');
        });
    }

    public function down(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            $table->dropColumn('admission_details');
        });
    }
};
