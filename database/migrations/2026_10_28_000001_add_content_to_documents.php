<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Store document files (PAR, referral slips, supporting files, etc.) in the
    // database as base64 so they survive backend redeploys. Render's free disk
    // is wiped on every deploy, which made downloads (e.g. the student's PAR) fail.
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->longText('content')->nullable()->after('file_size'); // base64 of the file
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn('content');
        });
    }
};
