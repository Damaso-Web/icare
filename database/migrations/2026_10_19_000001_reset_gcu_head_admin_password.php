<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

// One-time recovery: puts the GCU Head, GCU Staff and Genry Tester accounts back
// on their seeded default passwords, re-activates them, clears any sign-in lock,
// and asks for a new password at the next login. Only existing accounts are
// changed, except the GCU Head (admin@bsu.edu.ph), which is created if missing.
return new class extends Migration
{
    public function up(): void
    {
        $accounts = [
            'admin@bsu.edu.ph'       => 'Admin@iCARE2026',
            'gcu@bsu.edu.ph'         => 'GCU@iCARE2026',
            'genrytester@bsu.edu.ph' => 'Shazam@iCARE2026',
        ];

        foreach ($accounts as $email => $password) {
            $values = [
                'password'                 => Hash::make($password),
                'is_active'                => true,
                'must_change_password'     => true,
                'temp_password_expires_at' => null,
                'updated_at'               => now(),
            ];

            if (DB::table('users')->where('email', $email)->exists()) {
                DB::table('users')->where('email', $email)->update($values);
            } elseif ($email === 'admin@bsu.edu.ph') {
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
    }

    public function down(): void
    {
        // Nothing to undo.
    }
};
