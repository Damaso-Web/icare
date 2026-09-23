<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','gcu_staff','sdu_head','tmdu_staff','faculty','dean_secretary','system_admin') NOT NULL DEFAULT 'faculty'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','gcu_staff','sdu_head','tmdu_staff','faculty','dean_secretary') NOT NULL DEFAULT 'faculty'");
    }
};