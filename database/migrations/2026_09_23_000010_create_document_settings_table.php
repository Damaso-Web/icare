<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_settings', function (Blueprint $table) {
            $table->id();
            $table->string('document_code')->unique(); // e.g. 'QF-OSS-01'
            $table->string('revision_no')->default('01');
            $table->date('effectivity_date')->nullable();
            $table->string('ctrl_no_year', 4)->nullable();   // e.g. '26'
            $table->string('ctrl_no_term', 1)->nullable();   // '1', '2', or 'S'
            $table->timestamps();
        });

        // Seed the referral form's current known values so nothing regresses
        // from the static header that used to be hardcoded in the Vue files.
        DB::table('document_settings')->insert([
            'document_code'     => 'QF-OSS-01',
            'revision_no'       => '01',
            'effectivity_date'  => '2023-07-04',
            'ctrl_no_year'      => (string) (now()->year % 100),
            'ctrl_no_term'      => '1',
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('document_settings');
    }
};