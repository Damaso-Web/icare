<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Store complaint attachment files in the database (base64) instead of the
    // server's disk. Render's free tier wipes uploaded files on every redeploy,
    // which made attachments 404; the database (Aiven) is persistent.
    public function up(): void
    {
        Schema::table('complaint_attachments', function (Blueprint $table) {
            $table->string('mime_type')->nullable()->after('original_filename');
            $table->longText('content')->nullable()->after('mime_type'); // base64 of the file
        });
    }

    public function down(): void
    {
        Schema::table('complaint_attachments', function (Blueprint $table) {
            $table->dropColumn(['mime_type', 'content']);
        });
    }
};
