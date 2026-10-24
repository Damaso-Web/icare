<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// B545: the father/mother suffix added in 2026_10_23_000001 (B523) missed the
// Legal Guardian, who gets a name suffix (Jr., Sr., II, ...) too.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('guardian_suffix', 20)->nullable()->after('guardian_last_name');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('guardian_suffix');
        });
    }
};