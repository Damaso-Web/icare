<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // TMDU Head: a TMDU account that also manages the TMDU Staff accounts and
    // can override TMDU appointment statuses. Only the allowed values of the
    // role column change; no existing account is touched.
    private const WITH_HEAD    = ['admin', 'gcu_staff', 'sdu_head', 'tmdu_head', 'tmdu_staff', 'faculty', 'dean', 'dept_chair', 'dean_secretary', 'system_admin'];
    private const WITHOUT_HEAD = ['admin', 'gcu_staff', 'sdu_head', 'tmdu_staff', 'faculty', 'dean', 'dept_chair', 'dean_secretary', 'system_admin'];

    public function up(): void
    {
        $this->allowRoles(self::WITH_HEAD);
    }

    public function down(): void
    {
        DB::table('users')->where('role', 'tmdu_head')->update(['role' => 'tmdu_staff']);
        $this->allowRoles(self::WITHOUT_HEAD);
    }

    private function allowRoles(array $roles): void
    {
        $quoted = "'" . implode("','", $roles) . "'";

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM($quoted) NOT NULL DEFAULT 'faculty'");
            return;
        }

        // SQLite (the RBAC test suite) keeps the allowed roles in a CHECK
        // clause inside the table's CREATE TABLE text. Rebuild the table with
        // the new list, the same way 2026_10_07_000001 does: under a new name
        // first, so no other table's foreign keys get rewritten.
        $createSql = DB::selectOne("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = 'users'")->sql;
        $newCreateSql = preg_replace('/check\s*\(\s*"role"\s+in\s*\([^)]*\)\)/i', 'check ("role" in (' . $quoted . '))', $createSql, 1);
        $newCreateSql = preg_replace('/create table\s+"?users"?/i', 'CREATE TABLE users_role_rebuild_new', $newCreateSql, 1);

        DB::statement($newCreateSql);
        DB::statement('INSERT INTO users_role_rebuild_new SELECT * FROM users');
        DB::statement('PRAGMA foreign_keys = OFF');
        DB::statement('DROP TABLE users');
        DB::statement('ALTER TABLE users_role_rebuild_new RENAME TO users');
        DB::statement('PRAGMA foreign_keys = ON');
    }
};
