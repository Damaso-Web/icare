<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('name');
            $table->string('last_name')->nullable()->after('first_name');
        });

        // Backfill existing users by splitting their name
        DB::table('users')->get()->each(function ($user) {
            $parts = explode(' ', $user->name, 2);
            DB::table('users')->where('id', $user->id)->update([
                'first_name' => $parts[0] ?? $user->name,
                'last_name'  => $parts[1] ?? '',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name']);
        });
    }
};