<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL can't modify an FK column while the constraint exists.
        // Drop → change → re-add, in three separate statements.
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign(['case_id']);
            $table->dropForeign(['staff_user_id']);
            $table->dropForeign(['created_by_user_id']);
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->foreignId('case_id')->nullable()->change();
            $table->foreignId('staff_user_id')->nullable()->change();
            $table->foreignId('created_by_user_id')->nullable()->change();
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->foreign('case_id')->references('id')->on('cases')->restrictOnDelete();
            $table->foreign('staff_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('created_by_user_id')->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign(['case_id']);
            $table->dropForeign(['staff_user_id']);
            $table->dropForeign(['created_by_user_id']);
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->foreignId('case_id')->nullable(false)->change();
            $table->foreignId('staff_user_id')->nullable(false)->change();
            $table->foreignId('created_by_user_id')->nullable(false)->change();
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->foreign('case_id')->references('id')->on('cases')->restrictOnDelete();
            $table->foreign('staff_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('created_by_user_id')->references('id')->on('users')->restrictOnDelete();
        });
    }
};