<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->text('follow_up_notes')->nullable()->after('requires_follow_up');
            $table->timestamp('follow_up_flagged_at')->nullable()->after('follow_up_notes');
            $table->foreignId('follow_up_flagged_by')->nullable()->constrained('users')->nullOnDelete()->after('follow_up_flagged_at');
        });
    }

    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->dropColumn(['follow_up_notes', 'follow_up_flagged_at', 'follow_up_flagged_by']);
        });
    }
};
