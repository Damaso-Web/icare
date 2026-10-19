<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academic_years', function (Blueprint $table) {
            $table->string('status')->default('active')->after('midyear_end');
            $table->boolean('is_current')->default(false)->after('status');
        });

        // Mark the year that today falls in (or else the latest one that has started) as the current term.
        $today = now()->toDateString();
        $row = DB::table('academic_years')->where('year_start', '<=', $today)->where('year_end', '>=', $today)->first()
            ?: DB::table('academic_years')->where('year_start', '<=', $today)->orderByDesc('year_start')->first()
            ?: DB::table('academic_years')->orderByDesc('year_start')->first();
        if ($row) {
            DB::table('academic_years')->where('id', $row->id)->update(['is_current' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('academic_years', function (Blueprint $table) {
            $table->dropColumn(['status', 'is_current']);
        });
    }
};