<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // TMDU Head: a TMDU account that also manages the TMDU Staff accounts and
    // can override TMDU appointment statuses. Only the allowed values of the
    // role column change; no existing account is touched.
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;   // SQLite test databases do not enforce this list the same way
        }
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','gcu_staff','sdu_head','tmdu_head','tmdu_staff','faculty','dean','dept_chair','dean_secretary','system_admin') NOT NULL DEFAULT 'faculty'");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        DB::table('users')->where('role', 'tmdu_head')->update(['role' => 'tmdu_staff']);
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','gcu_staff','sdu_head','tmdu_staff','faculty','dean','dept_chair','dean_secretary','system_admin') NOT NULL DEFAULT 'faculty'");
    }
};
