<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// `status` is filtered on in nearly every list/queue/dashboard query
// (referrals, complaints, cases, testing_records, appointments) but had no
// index - only foreign key columns did (Laravel adds those automatically
// via foreignId()->constrained()). As these tables grow past a few hundred
// rows, every status-filtered query (which is most of the app's traffic:
// dashboards, queues, "pending"/"completed" filters) does a full table scan
// instead of an index lookup. appointments.appointment_date is indexed too,
// since "today's appointments" is a dashboard stat computed on every load.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            $table->index('status');
        });
        Schema::table('complaints', function (Blueprint $table) {
            $table->index('status');
        });
        Schema::table('cases', function (Blueprint $table) {
            $table->index('status');
        });
        Schema::table('testing_records', function (Blueprint $table) {
            $table->index('status');
        });
        Schema::table('appointments', function (Blueprint $table) {
            $table->index('status');
            $table->index('appointment_date');
        });
    }

    public function down(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            $table->dropIndex(['status']);
        });
        Schema::table('complaints', function (Blueprint $table) {
            $table->dropIndex(['status']);
        });
        Schema::table('cases', function (Blueprint $table) {
            $table->dropIndex(['status']);
        });
        Schema::table('testing_records', function (Blueprint $table) {
            $table->dropIndex(['status']);
        });
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['appointment_date']);
        });
    }
};