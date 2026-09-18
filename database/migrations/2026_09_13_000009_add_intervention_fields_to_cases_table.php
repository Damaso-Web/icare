<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->foreignId('intervention_person_in_charge_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('intervention_date')->nullable();
            $table->boolean('intervention_excused')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->dropColumn(['intervention_person_in_charge_id', 'intervention_date', 'intervention_excused']);
        });
    }
};
