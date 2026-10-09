<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Students get their own reset-token table so a staff account and a student
// account that share an email address can no longer overwrite each other's token.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_password_reset_tokens');
    }
};