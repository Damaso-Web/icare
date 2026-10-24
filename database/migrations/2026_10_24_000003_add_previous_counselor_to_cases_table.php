<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// B562: when a case is reassigned to another GCU counselor, the outgoing
// counselor must lose edit/action access but keep read-only visibility
// under a "Reassigned/History" view - same previous_<role>_user_id pattern
// used for B559's TestingRecord tester reassignment.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->foreignId('previous_counselor_id')->nullable()->after('primary_counselor_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('previous_counselor_id');
        });
    }
};