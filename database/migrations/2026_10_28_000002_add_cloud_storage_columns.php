<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Documents and OR photos now upload straight to Cloudinary instead of
// local disk (wiped on every Render deploy) or base64-in-MySQL (bloats the
// table and slows every query that touches it). These columns are how we
// find a file back on Cloudinary; existing rows with old 'content'/'path'
// data are untouched and still served by the fallback in
// Document::streamResponse().
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->string('cloud_public_id')->nullable()->after('disk');
            $table->string('cloud_resource_type')->nullable()->after('cloud_public_id');
            $table->string('cloud_format')->nullable()->after('cloud_resource_type');
        });

        Schema::table('testing_records', function (Blueprint $table) {
            $table->string('or_photo_disk')->default('local')->after('or_photo_path');
            $table->string('or_photo_cloud_public_id')->nullable()->after('or_photo_disk');
            $table->string('or_photo_cloud_resource_type')->nullable()->after('or_photo_cloud_public_id');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn(['cloud_public_id', 'cloud_resource_type', 'cloud_format']);
        });

        Schema::table('testing_records', function (Blueprint $table) {
            $table->dropColumn(['or_photo_disk', 'or_photo_cloud_public_id', 'or_photo_cloud_resource_type']);
        });
    }
};