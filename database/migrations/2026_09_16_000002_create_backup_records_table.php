<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_records', function (Blueprint $table) {
            $table->id();
            $table->string('type');    // data | config
            $table->string('trigger'); // scheduled | manual
            $table->string('status')->default('pending'); // pending | completed | failed
            $table->boolean('verified')->default(false);
            $table->string('file_path')->nullable();
            $table->json('row_counts')->nullable();
            $table->text('error_message')->nullable();
            $table->foreignId('initiated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_records');
    }
};
