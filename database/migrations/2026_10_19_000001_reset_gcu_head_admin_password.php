<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

// One-time recovery: puts the GCU Head (admin@bsu.edu.ph) back on the seeded
// default password, re-activates it, clears any sign-in lock, and asks for a
// new password at the next login. Creates the account if it is missing.
return new class extends Migration
{
    public function up(): void
    {
        $email = 'admin@bsu.edu.ph';
        $values = [
            'password'                 => Hash::make('Admin@iCARE2026'),
            'is_active'                => true,
            'must_change_password'     => true,
            'temp_password_expires_at' => null,
            'updated_at'               => now(),
        ];

        if (DB::table('users')->where('email', $email)->exists()) {
            DB::table('users')->where('email', $email)->update($values);
        } else {
            DB::table('users')->insert($values + [
                'name'        => 'Administrator',
                'email'       => $email,
                'employee_id' => 'BSU-ADMIN-001',
                'role'        => 'admin',
                'unit'        => 'OSS',
                'created_at'  => now(),
            ]);
        }

        $key = 'login_guard:staff:' . sha1($email);
        Cache::forget($key . ':lock');
        Cache::forget($key . ':count');
    }

    public function down(): void
    {
        // Nothing to undo.
    }
};
