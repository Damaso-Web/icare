<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->string('complainant_name')->nullable()->after('filed_by_user_id');
            $table->string('complainant_address')->nullable()->after('complainant_name');

            $table->string('complainee_position')->nullable()->after('complainee_student_id');
            $table->string('complainee_college')->nullable()->after('complainee_position');
            $table->string('complainee_department')->nullable()->after('complainee_college');
            $table->string('complainee_office')->nullable()->after('complainee_department');
            $table->string('complainee_address')->nullable()->after('complainee_office');

            $table->boolean('certification_agreed')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->dropColumn([
                'complainant_name',
                'complainant_address',
                'complainee_position',
                'complainee_college',
                'complainee_department',
                'complainee_office',
                'complainee_address',
                'certification_agreed',
            ]);
        });
    }
};