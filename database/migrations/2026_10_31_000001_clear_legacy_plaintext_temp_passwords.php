<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * B643 follow-up (10/31/2026): before the hash-only change landed on
 * 2026-10-30, admin reset/create paths wrote the temp password into the
 * `temp_password` column as plaintext. Any such row still sitting in the
 * database is a live, working password a DB leak would hand out.
 *
 * This migration nulls every non-hash value in students.temp_password and
 * users.temp_password, and re-flags the affected accounts with
 * must_change_password = true so the next login forces a reset through
 * the (already-hashed) flow.
 *
 * Irreversible by design: the plaintext values must not come back.
 */
return new class extends Migration
{
    public function up(): void
{
    foreach (['students', 'users'] as $table) {
        // Plaintext from Str::random(10) is 10 chars; hashes are 60+
        // (bcrypt) or ~95 (argon2). Length is algorithm-agnostic and
        // works on both MySQL and SQLite; a NOT-LIKE '$2y$%' check
        // would miss argon2 and over-null valid hashes.
        DB::table($table)
            ->whereNotNull('temp_password')
            ->whereRaw('LENGTH(temp_password) < 20')
            ->update([
                'temp_password'            => null,
                'temp_password_expires_at' => null,
                'must_change_password'     => true,
            ]);
    }
}

    public function down(): void
    {
        // Irreversible. Nulling plaintext passwords is the whole point -
        // restoring them would recreate the vulnerability.
    }
};