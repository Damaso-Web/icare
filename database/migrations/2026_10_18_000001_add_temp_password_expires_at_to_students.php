<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A one-time password sent when a student registers works as a password only
// for a short time. NULL = the password does not expire (every other case).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->timestamp('temp_password_expires_at')->nullable()->after('must_change_password');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('temp_password_expires_at');
        });
    }
};