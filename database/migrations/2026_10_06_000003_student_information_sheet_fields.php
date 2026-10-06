<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            foreach ([
                'civil_status'  => 50,
                'nationality'   => 100,
                'birthplace'    => 255,
                'languages'     => 255,
                'father_age'    => 3,
                'father_educational_attainment'   => 255,
                'mother_age'    => 3,
                'mother_educational_attainment'   => 255,
                'guardian_age'  => 3,
                'guardian_occupation'             => 255,
                'guardian_educational_attainment' => 255,
                'senior_high_school'              => 255,
                'senior_high_year_graduated'      => 4,
            ] as $col => $len) {
                if (!Schema::hasColumn('students', $col)) {
                    $table->string($col, $len)->nullable();
                }
            }
            foreach (['senior_high_achievements', 'high_school_achievements', 'elementary_achievements'] as $col) {
                if (!Schema::hasColumn('students', $col)) {
                    $table->text($col)->nullable();
                }
            }
        });

        Schema::table('appointments', function (Blueprint $table) {
            if (!Schema::hasColumn('appointments', 'on_hold_at')) {
                $table->timestamp('on_hold_at')->nullable();
            }
        });

        Schema::table('parent_conference_slips', function (Blueprint $table) {
            if (!Schema::hasColumn('parent_conference_slips', 'referral_id')) {
                $table->foreignId('referral_id')->nullable()->constrained('referrals')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('parent_conference_slips', function (Blueprint $table) {
            if (Schema::hasColumn('parent_conference_slips', 'referral_id')) {
                $table->dropConstrainedForeignId('referral_id');
            }
        });
        Schema::table('appointments', function (Blueprint $table) {
            if (Schema::hasColumn('appointments', 'on_hold_at')) {
                $table->dropColumn('on_hold_at');
            }
        });
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(array_filter([
                'civil_status','nationality','birthplace','languages','father_age','father_educational_attainment',
                'mother_age','mother_educational_attainment','guardian_age','guardian_occupation',
                'guardian_educational_attainment','senior_high_school','senior_high_year_graduated',
                'senior_high_achievements','high_school_achievements','elementary_achievements',
            ], fn($c) => Schema::hasColumn('students', $c)));
        });
    }
};