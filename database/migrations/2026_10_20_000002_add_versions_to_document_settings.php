<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Document headers become versioned: saving creates a new version instead of
// overwriting, exactly one version per document code is active, and every version
// remembers when it was active so older documents keep the header they were made with.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_settings', function (Blueprint $table) {
            $table->dropUnique(['document_code']);
        });
        Schema::table('document_settings', function (Blueprint $table) {
            $table->index('document_code');
            $table->unsignedInteger('version_no')->default(1)->after('document_code');
            $table->string('status')->default('active')->after('ctrl_no_term');   // active | archived
            $table->boolean('is_active')->default(true)->after('status');
            $table->timestamp('activated_at')->nullable()->after('is_active');
            $table->timestamp('deactivated_at')->nullable()->after('activated_at');
            $table->unsignedBigInteger('created_by_user_id')->nullable()->after('deactivated_at');
        });

        // Existing rows are version 1, active since the start, so every older document maps to them.
        DB::table('document_settings')->update(['activated_at' => '2000-01-01 00:00:00']);
    }

    public function down(): void
    {
        Schema::table('document_settings', function (Blueprint $table) {
            $table->dropIndex(['document_code']);
            $table->dropColumn(['version_no', 'status', 'is_active', 'activated_at', 'deactivated_at', 'created_by_user_id']);
        });
        Schema::table('document_settings', function (Blueprint $table) {
            $table->unique('document_code');
        });
    }
};