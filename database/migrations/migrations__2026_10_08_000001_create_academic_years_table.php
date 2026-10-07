<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_years', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('start_year')->unique(); // 2026 = A.Y. 2026-2027
            $table->date('year_start');
            $table->date('year_end');
            $table->date('first_sem_start');
            $table->date('first_sem_end');
            $table->date('second_sem_start');
            $table->date('second_sem_end');
            $table->date('midyear_start');
            $table->date('midyear_end');
            $table->timestamps();
        });

        // Seed the current A.Y. with the dates the Reports page used before
        // they became configurable (Aug 1 - Jul 31).
        $y = now()->month >= 8 ? now()->year : now()->year - 1;
        DB::table('academic_years')->insert([
            'start_year'       => $y,
            'year_start'       => "$y-08-01",
            'year_end'         => ($y + 1) . '-07-31',
            'first_sem_start'  => "$y-08-01",
            'first_sem_end'    => "$y-12-31",
            'second_sem_start' => ($y + 1) . '-01-01',
            'second_sem_end'   => ($y + 1) . '-05-31',
            'midyear_start'    => ($y + 1) . '-06-01',
            'midyear_end'      => ($y + 1) . '-07-31',
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_years');
    }
};