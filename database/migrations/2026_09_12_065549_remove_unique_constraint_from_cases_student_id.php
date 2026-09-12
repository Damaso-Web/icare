<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->dropForeign('cases_student_id_foreign');
            $table->dropUnique('cases_student_id_unique');
        });

        Schema::table('cases', function (Blueprint $table) {
            $table->index('student_id');
            $table->foreign('student_id')->references('id')->on('students')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->dropForeign(['student_id']);
            $table->dropIndex(['student_id']);
        });

        Schema::table('cases', function (Blueprint $table) {
            $table->unique('student_id');
            $table->foreign('student_id')->references('id')->on('students')->onDelete('restrict');
        });
    }
};