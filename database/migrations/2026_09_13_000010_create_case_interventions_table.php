<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_interventions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->text('description');
            $table->foreignId('person_in_charge_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('excused')->nullable();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Carry any existing single-entry intervention data over as the first log entry.
        $cases = DB::table('cases')
            ->whereNotNull('background_info')
            ->orWhereNotNull('intervention_person_in_charge_id')
            ->orWhereNotNull('intervention_excused')
            ->get();

        foreach ($cases as $case) {
            if (empty($case->background_info) && empty($case->intervention_person_in_charge_id) && $case->intervention_excused === null) {
                continue;
            }
            $timestamp = $case->intervention_date ?? $case->updated_at ?? now();
            DB::table('case_interventions')->insert([
                'case_id'              => $case->id,
                'description'          => $case->background_info ?? '',
                'person_in_charge_id'  => $case->intervention_person_in_charge_id,
                'excused'              => $case->intervention_excused,
                'recorded_by_user_id'  => null,
                'created_at'           => $timestamp,
                'updated_at'           => $timestamp,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('case_interventions');
    }
};
