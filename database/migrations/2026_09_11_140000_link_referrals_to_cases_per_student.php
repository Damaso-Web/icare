<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Reconstructed to match this project's live database history.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('referrals', 'case_id')) {
            return;
        }

        Schema::table('referrals', function (Blueprint $table) {
            $table->foreignId('case_id')->nullable()->after('student_id')->constrained('cases')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            $table->dropForeign(['case_id']);
            $table->dropColumn('case_id');
        });
    }
};
