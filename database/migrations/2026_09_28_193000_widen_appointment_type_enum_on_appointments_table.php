<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The `appointment_type` column is a DB-level enum that never included
     * 'fee_form_pickup' or 'par_release', even though
     * TestingRecordController::acknowledge()/schedulePAR() (part of the TMDU
     * testing workflow) already write those exact values. That mismatch
     * causes "Data truncated for column 'appointment_type'" errors whenever
     * a psychological_testing referral is acknowledged or its PAR pickup is
     * scheduled - this is a pure widen (no renaming of existing values), so
     * it's safe to run directly with no intermediate data migration step.
     */
    public function up(): void
    {
        // Raw MySQL enum syntax - no-op on SQLite (used for the RBAC test
        // suite), which has no MODIFY COLUMN and stores appointment_type
        // loosely-typed anyway, so every value here is already accepted.
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE appointments MODIFY COLUMN appointment_type ENUM(
            'initial_counseling',
            'follow_up_session',
            'psychological_testing',
            'disciplinary_conference',
            'parent_conference',
            'academic_coaching',
            'consultation',
            'fee_form_pickup',
            'par_release'
        )");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE appointments MODIFY COLUMN appointment_type ENUM(
            'initial_counseling',
            'follow_up_session',
            'psychological_testing',
            'disciplinary_conference',
            'parent_conference',
            'academic_coaching',
            'consultation'
        )");
    }
};