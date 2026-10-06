<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','gcu_staff','sdu_head','tmdu_staff','faculty','dean','dept_chair','dean_secretary','system_admin') NOT NULL DEFAULT 'faculty'");
            return;
        }

        // SQLite (used for the RBAC test suite) enforces enum() columns via
        // a CHECK constraint baked directly into the table's CREATE TABLE
        // text, unlike MySQL's real ENUM type. Doctrine/DBAL's table
        // rebuild (via Schema::change()) doesn't actually regenerate that
        // CHECK clause - it treats old and new column as the same type and
        // copies the original CHECK text over verbatim, so the constraint
        // never widens. Instead, rebuild the table by hand the way SQLite's
        // own docs recommend for changing a CHECK: read the table's real
        // CREATE TABLE SQL, swap in the new allowed-values list, and
        // recreate it under the same name.
        $this->rebuildSqliteRoleCheck([
            'admin', 'gcu_staff', 'sdu_head', 'tmdu_staff', 'faculty', 'dean', 'dept_chair', 'dean_secretary', 'system_admin',
        ]);
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','gcu_staff','sdu_head','tmdu_staff','faculty','dean_secretary','system_admin') NOT NULL DEFAULT 'faculty'");
            return;
        }

        $this->rebuildSqliteRoleCheck([
            'admin', 'gcu_staff', 'sdu_head', 'tmdu_staff', 'faculty', 'dean_secretary', 'system_admin',
        ]);
    }

    private function rebuildSqliteRoleCheck(array $allowedRoles): void
    {
        $quoted = "'" . implode("','", $allowedRoles) . "'";

        $createSql = DB::selectOne(
            "SELECT sql FROM sqlite_master WHERE type = 'table' AND name = 'users'"
        )->sql;

        // Build the replacement under a TEMPORARY name first, rather than
        // renaming the real "users" table away. Renaming "users" itself
        // would trigger SQLite's automatic (and here, unwanted) rewrite of
        // every OTHER table's foreign key text that points at "users" -
        // e.g. cases.primary_counselor_id would end up permanently
        // pointing at "users_role_rebuild_tmp" once that temp table is
        // dropped, breaking every table with a users FK. Building the
        // replacement under its own new name means nothing references it
        // yet, so that rewrite never fires; only the final rename (of the
        // temp table, which nothing points to) is needed, and that's a
        // genuine no-op for every other table's FK text.
        $newCreateSql = preg_replace(
            '/check\s*\(\s*"role"\s+in\s*\([^)]*\)\)/i',
            'check ("role" in (' . $quoted . '))',
            $createSql,
            1
        );
        $newCreateSql = preg_replace(
            '/create table\s+"?users"?/i',
            'CREATE TABLE users_role_rebuild_new',
            $newCreateSql,
            1
        );

        DB::statement($newCreateSql);
        DB::statement('INSERT INTO users_role_rebuild_new SELECT * FROM users');

        // FK enforcement has to be off only for this DROP - "users" briefly
        // doesn't exist while every other table's FK text still names it,
        // but the very next statement recreates it under the same name
        // with the same data, so nothing is left dangling once this
        // migration step finishes.
        DB::statement('PRAGMA foreign_keys = OFF');
        DB::statement('DROP TABLE users');
        DB::statement('ALTER TABLE users_role_rebuild_new RENAME TO users');
        DB::statement('PRAGMA foreign_keys = ON');
    }
};