<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

// One-time recovery: puts the GCU Head, GCU Staff and Genry Tester accounts back
// on their seeded default passwords (only if they exist), re-activates them,
// clears any sign-in lock, and asks for a new password at the next login.
// It never creates accounts and never fails the deploy. It prints the emails of
// the admin / GCU accounts that really exist so they show up in the deploy log.
return new class extends Migration
{
    public function up(): void
    {
        try {
            $accounts = [
                'admin@bsu.edu.ph'       => 'Admin@iCARE2026',
                'gcu@bsu.edu.ph'         => 'GCU@iCARE2026',
                'genrytester@bsu.edu.ph' => 'Shazam@iCARE2026',
            ];

            foreach ($accounts as $email => $password) {
                $query = DB::table('users')->where('email', $email);
                if (!$query->exists()) {
                    echo "  [reset] no account with email {$email}\n";
                    continue;
                }
                $query->update([
                    'password'                 => Hash::make($password),
                    'is_active'                => true,
                    'must_change_password'     => true,
                    'temp_password_expires_at' => null,
                    'updated_at'               => now(),
                ]);
                $key = 'login_guard:staff:' . sha1($email);
                Cache::forget($key . ':lock');
                Cache::forget($key . ':count');
                echo "  [reset] password reset for {$email}\n";
            }

            $rows = DB::table('users')->whereIn('role', ['admin', 'gcu_staff'])->orderBy('id')->get(['email', 'role', 'is_active']);
            foreach ($rows as $r) {
                echo "  [reset] existing {$r->role}: {$r->email} (active=" . (int) $r->is_active . ")\n";
            }
        } catch (\Throwable $e) {
            echo '  [reset] skipped: ' . $e->getMessage() . "\n";
        }
    }

    public function down(): void
    {
        // Nothing to undo.
    }
};

