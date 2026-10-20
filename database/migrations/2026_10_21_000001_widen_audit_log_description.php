<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

// Audit descriptions (password resets, reactivations with reasons and notes) can
// be longer than 255 characters, which made MySQL refuse the insert and the
// whole request fail with a server error.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function ($table) {
            $table->text('description')->change();
        });
    }

    public function down(): void
    {
        // Left as text: shrinking would truncate or fail on existing rows.
    }
};