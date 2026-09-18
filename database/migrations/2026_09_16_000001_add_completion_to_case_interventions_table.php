<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('case_interventions', function (Blueprint $table) {
            $table->boolean('is_completed')->default(false)->after('excused');
            $table->timestamp('completed_at')->nullable()->after('is_completed');
            $table->foreignId('completed_by_user_id')->nullable()->after('completed_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('case_interventions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('completed_by_user_id');
            $table->dropColumn(['is_completed', 'completed_at']);
        });
    }
};
