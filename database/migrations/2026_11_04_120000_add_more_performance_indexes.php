<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// B#, 11/4/2026: performance follow-up to 2026_10_29_000001's status
// indexes. `role`/`is_active` on users and `college`/`is_active` on
// students are filtered on far more often than status is - every
// notification dispatch (who gets notified of a new referral/complaint),
// every staff/faculty/student list, and every role check that scopes a
// dashboard query goes through one of these - but neither table had
// anything beyond its unique key. With the DB on a separate host (Aiven)
// from the app (Render), every one of those became a full-table-scan
// round trip instead of an index lookup, on practically every request.
// created_at is added on referrals/complaints/audit_logs since dashboards
// and the audit log view sort "most recent first" on it with no index at
// all, which means a filesort over the whole table as it grows.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->index(['role', 'is_active']);
            $table->index('college');
            $table->index('department');
        });
        Schema::table('students', function (Blueprint $table) {
            $table->index(['college', 'is_active']);
        });
        Schema::table('referrals', function (Blueprint $table) {
            $table->index('created_at');
        });
        Schema::table('complaints', function (Blueprint $table) {
            $table->index('created_at');
        });
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role', 'is_active']);
            $table->dropIndex(['college']);
            $table->dropIndex(['department']);
        });
        Schema::table('students', function (Blueprint $table) {
            $table->dropIndex(['college', 'is_active']);
        });
        Schema::table('referrals', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
        });
        Schema::table('complaints', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
        });
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
        });
    }
};