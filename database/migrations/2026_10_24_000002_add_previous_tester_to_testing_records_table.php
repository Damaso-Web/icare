<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// B559: when a testing record is reassigned to a new tester, the previous
// tester otherwise drops out of TestingRecordController::index()'s scoping
// entirely (assigned_tester_user_id no longer matches them, and it's no
// longer null either) and loses all visibility of a case they used to
// handle. Recording who it was last assigned to lets index() keep showing
// it to them - read-only, marked "Reassigned" - instead of just vanishing.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('testing_records', function (Blueprint $table) {
            $table->foreignId('previous_tester_user_id')->nullable()->after('assigned_tester_user_id')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('testing_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('previous_tester_user_id');
        });
    }
};