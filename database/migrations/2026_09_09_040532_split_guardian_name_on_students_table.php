<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('guardian_first_name')->nullable()->after('guardian_name');
            $table->string('guardian_middle_name')->nullable()->after('guardian_first_name');
            $table->string('guardian_last_name')->nullable()->after('guardian_middle_name');
        });

        // Backfill from existing guardian_name if present
        DB::table('students')->whereNotNull('guardian_name')->get()->each(function ($student) {
            $parts = explode(' ', $student->guardian_name, 2);
            DB::table('students')->where('id', $student->id)->update([
                'guardian_first_name' => $parts[0] ?? $student->guardian_name,
                'guardian_last_name'  => $parts[1] ?? '',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['guardian_first_name', 'guardian_middle_name', 'guardian_last_name']);
        });
    }
};