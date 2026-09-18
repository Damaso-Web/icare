<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('case_interventions', function (Blueprint $table) {
            $table->string('type')->default('other')->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('case_interventions', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};