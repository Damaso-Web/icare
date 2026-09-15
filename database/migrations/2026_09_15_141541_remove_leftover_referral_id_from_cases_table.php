<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            if (Schema::hasColumn('cases', 'referral_id')) {
                $table->dropForeign(['referral_id']);
                $table->dropColumn('referral_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->foreignId('referral_id')->nullable()->constrained('referrals')->nullOnDelete();
        });
    }
};