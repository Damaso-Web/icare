<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('testing_records', function (Blueprint $table) {
            // The student-uploaded OR (proof of fee payment) that gates
            // TMDU setting the actual testing appointment. Kept as its own
            // simple pair of columns rather than routed through the shared
            // Document system, since Document::uploaded_by_user_id is a
            // staff User FK and this upload comes from the student guard.
            $table->string('or_photo_path')->nullable()->after('report_sent_at');
            $table->string('or_photo_original_name')->nullable()->after('or_photo_path');
            $table->timestamp('or_uploaded_at')->nullable()->after('or_photo_original_name');
        });
    }

    public function down(): void
    {
        Schema::table('testing_records', function (Blueprint $table) {
            $table->dropColumn(['or_photo_path', 'or_photo_original_name', 'or_uploaded_at']);
        });
    }
};