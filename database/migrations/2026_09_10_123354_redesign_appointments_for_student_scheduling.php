<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->string('scheduling_token')->nullable()->unique()->after('id');
            $table->timestamp('token_expires_at')->nullable()->after('scheduling_token');
            $table->enum('request_status', ['awaiting_student', 'pending_confirmation', 'confirmed', 'rescheduled', 'cancelled', 'completed', 'no_show'])
                  ->default('awaiting_student')->after('status');
            $table->string('call_slip_stage')->nullable()->after('request_status');
            $table->timestamp('call_slip_initiated_at')->nullable();
            $table->text('call_slip_notes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn([
                'scheduling_token', 'token_expires_at', 'request_status',
                'call_slip_stage', 'call_slip_initiated_at', 'call_slip_notes'
            ]);
        });
    }
};