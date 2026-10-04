<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Counts actual no-shows across every appointment for this case, so a
    // student who simply doesn't show up (without ever requesting a
    // reschedule) still gets flagged after enough repeats. Previously the
    // only escalation path was AppointmentController::
    // requestRescheduleByStudent() hitting RESCHEDULE_LIMIT - a student who
    // never asks to reschedule but repeatedly no-shows was never counted.
    // Kept on the case (not the appointment) because a no-show's escalation
    // needs to persist across separate booking cycles, not reset every time
    // a new Appointment row is created for the next attempt.
    public function up(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->unsignedInteger('no_show_count')->default(0)->after('status_changed_at');
        });
    }

    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->dropColumn('no_show_count');
        });
    }
};