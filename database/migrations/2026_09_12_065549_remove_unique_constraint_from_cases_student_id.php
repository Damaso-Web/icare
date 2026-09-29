<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            Schema::table('cases', function (Blueprint $table) {
                $table->dropForeign('cases_student_id_foreign');
            });
        }

        // Dropping the constraint call itself only queues the command - the
        // actual SQL runs when Schema::table() returns, so the try/catch has
        // to wrap the whole call, not just $table->dropUnique(). SQLite
        // (used in tests) can create this unique constraint under a
        // different auto-generated index name than MySQL, so there may be
        // nothing named "cases_student_id_unique" to drop there - safe to
        // ignore since the goal is just making sure it's gone.
        try {
            Schema::table('cases', function (Blueprint $table) {
                $table->dropUnique('cases_student_id_unique');
            });
        } catch (\Throwable $e) {
            //
        }

        Schema::table('cases', function (Blueprint $table) {
            $table->index('student_id');
            $table->foreign('student_id')->references('id')->on('students')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            Schema::table('cases', function (Blueprint $table) {
                $table->dropForeign(['student_id']);
            });
        }

        try {
            Schema::table('cases', function (Blueprint $table) {
                $table->dropIndex(['student_id']);
            });
        } catch (\Throwable $e) {
            //
        }

        Schema::table('cases', function (Blueprint $table) {
            $table->unique('student_id');
            $table->foreign('student_id')->references('id')->on('students')->onDelete('restrict');
        });
    }
};