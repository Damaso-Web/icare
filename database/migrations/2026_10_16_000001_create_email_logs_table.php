<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per notification email: what was sent, to whom, and whether it
        // went through. Failed emails keep a copy of the notification so they can be retried.
        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->string('notifiable_type');
            $table->unsignedBigInteger('notifiable_id');
            $table->string('recipient_email');
            $table->string('notification');                 // notification class
            $table->string('subject')->nullable();
            $table->string('status', 20)->default('sent')->index(); // sent | failed | gave_up
            $table->unsignedSmallInteger('attempts')->default(1);
            $table->text('error_message')->nullable();
            $table->longText('payload')->nullable();        // serialized notification, kept only while failed
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->index(['notifiable_type', 'notifiable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_logs');
    }
};