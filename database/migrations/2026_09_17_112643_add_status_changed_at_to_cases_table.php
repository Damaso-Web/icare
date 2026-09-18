<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->timestamp('status_changed_at')->nullable();
        });

        // Backfill existing rows so the inactivity clock starts from "now"
        // instead of every case looking like it's been stale forever.
        DB::table('cases')->whereNull('status_changed_at')->update(['status_changed_at' => now()]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->dropColumn('status_changed_at');
        });
    }
};
