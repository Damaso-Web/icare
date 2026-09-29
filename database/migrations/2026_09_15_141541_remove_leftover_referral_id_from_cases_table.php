<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite refuses to DROP COLUMN on a column that carries a
        // REFERENCES clause (the FK is baked into the table definition
        // there, not a separately droppable named constraint like MySQL's),
        // so this whole cleanup step is MySQL-only - leaving the leftover
        // column in place on SQLite (used for the RBAC test suite) is
        // harmless.
        if (DB::connection()->getDriverName() === 'mysql') {
            Schema::table('cases', function (Blueprint $table) {
                if (Schema::hasColumn('cases', 'referral_id')) {
                    $table->dropForeign(['referral_id']);
                    $table->dropColumn('referral_id');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->foreignId('referral_id')->nullable()->constrained('referrals')->nullOnDelete();
        });
    }
};