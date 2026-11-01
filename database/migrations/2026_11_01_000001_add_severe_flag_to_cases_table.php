<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            if (!Schema::hasColumn('cases', 'is_severe')) {
                $table->boolean('is_severe')->default(false)->after('status');
            }
            if (!Schema::hasColumn('cases', 'severe_reason')) {
                $table->text('severe_reason')->nullable()->after('is_severe');
            }
            if (!Schema::hasColumn('cases', 'severe_flagged_at')) {
                $table->timestamp('severe_flagged_at')->nullable()->after('severe_reason');
            }
            if (!Schema::hasColumn('cases', 'severe_flagged_by')) {
                $table->foreignId('severe_flagged_by')->nullable()->after('severe_flagged_at')
                      ->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('cases', 'severe_flagged_by')) {
            Schema::table('cases', fn(Blueprint $t) => $t->dropForeign(['severe_flagged_by']));
        }

        Schema::table('cases', function (Blueprint $table) {
            foreach (['severe_flagged_by', 'severe_flagged_at', 'severe_reason', 'is_severe'] as $col) {
                if (Schema::hasColumn('cases', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};