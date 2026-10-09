<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Student Information Sheet (SIF) Ctrl No. is "<year>-<term>-<client no.>",
// e.g. 26-1-0012. This is the client no. part: which number client the
// student is. Editable by GCU on the SIF.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            if (!Schema::hasColumn('students', 'sif_client_no')) {
                $table->unsignedInteger('sif_client_no')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            if (Schema::hasColumn('students', 'sif_client_no')) {
                $table->dropColumn('sif_client_no');
            }
        });
    }
};