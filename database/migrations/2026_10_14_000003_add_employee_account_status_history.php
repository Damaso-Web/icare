<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('deactivation_reason')->nullable();
            $table->text('deactivation_note')->nullable();
            $table->timestamp('deactivated_at')->nullable();
        });

        // One row per deactivation / reactivation. Never deleted, so the
        // timeline survives repeated deactivate and reactivate cycles.
        Schema::create('user_status_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('action');                       // deactivated | reactivated
            $table->string('reason_key');
            $table->string('reason_label');
            $table->text('note')->nullable();
            $table->string('previous_reason_label')->nullable(); // reactivation: the original deactivation reason
            $table->timestamp('previous_at')->nullable();        // reactivation: when it was deactivated
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_status_events');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['deactivation_reason', 'deactivation_note', 'deactivated_at']);
        });
    }
};