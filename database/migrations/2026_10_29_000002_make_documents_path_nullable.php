<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// `documents.path` was NOT NULL from when every document lived on local
// disk. Cloudinary-backed documents (see 2026_10_28_000002_add_cloud_storage_columns.php)
// deliberately store `path => null` since there's no local path for them -
// they're identified by cloud_public_id instead. That made every Cloudinary
// document insert fail outright:
//   SQLSTATE[23000]: Integrity constraint violation: 1048 Column 'path'
//   cannot be null
// e.g. attaching a PAR report on Testing Records (sendToGcu()) was failing
// with a 500 for exactly this reason. Making the column nullable is the fix;
// nothing reads `path` without already checking `disk === 'cloudinary'`
// first (see Document::streamResponse()), so existing local-disk rows are
// unaffected.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->string('path')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->string('path')->nullable(false)->change();
        });
    }
};