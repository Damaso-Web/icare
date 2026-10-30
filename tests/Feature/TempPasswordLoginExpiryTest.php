<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Closes the last item in the guide's #6 remediation:
 * "Add tests proving ... expiry is enforced."
 *
 * After the 2026-10-31 patch, PasswordReset::perform() sets
 * temp_password_expires_at = now()+7d, which activates the login-time
 * expiry check that was already present in both controllers. These tests
 * lock in three behaviors per account type:
 *   1. An expired temp password is rejected at login.
 *   2. A valid temp password still logs in.
 *   3. A leftover expiry on an account with must_change_password=false
 *      does NOT lock out a user who has already set their own password.
 */
class TempPasswordLoginExpiryTest extends TestCase
{
    use RefreshDatabase;

    // ---------- Staff ----------

    public function test_expired_staff_temp_password_is_rejected_at_login(): void
    {
        User::factory()->create([
            'email'                    => 'expired.staff@bsu.edu.ph',
            'role'                     => 'gcu_staff',
            'password'                 => Hash::make('TempPass1!'),
            'temp_password'            => Hash::make('TempPass1!'),
            'must_change_password'     => true,
            'temp_password_expires_at' => now()->subDay(),
            'is_active'                => true,
        ]);

        $this->postJson('/api/login', [
            'email'    => 'expired.staff@bsu.edu.ph',
            'password' => 'TempPass1!',
            'audience' => 'oss',
        ])->assertStatus(401);
    }

    public function test_valid_staff_temp_password_is_accepted(): void
    {
        User::factory()->create([
            'email'                    => 'valid.staff@bsu.edu.ph',
            'role'                     => 'gcu_staff',
            'password'                 => Hash::make('TempPass1!'),
            'temp_password'            => Hash::make('TempPass1!'),
            'must_change_password'     => true,
            'temp_password_expires_at' => now()->addDay(),
            'is_active'                => true,
        ]);

        $this->postJson('/api/login', [
            'email'    => 'valid.staff@bsu.edu.ph',
            'password' => 'TempPass1!',
            'audience' => 'oss',
        ])->assertOk();
    }

    public function test_leftover_expiry_does_not_lock_staff_who_changed_their_password(): void
    {
        // Regression for the guard: even if changePassword() left
        // temp_password_expires_at set, must_change_password=false means
        // the owner is on their own password and must not be rejected.
        User::factory()->create([
            'email'                    => 'changed.staff@bsu.edu.ph',
            'role'                     => 'gcu_staff',
            'password'                 => Hash::make('MyOwnPass1!'),
            'temp_password'            => null,
            'must_change_password'     => false,
            'temp_password_expires_at' => now()->subDay(), // leftover from an old reset
            'is_active'                => true,
        ]);

        $this->postJson('/api/login', [
            'email'    => 'changed.staff@bsu.edu.ph',
            'password' => 'MyOwnPass1!',
            'audience' => 'oss',
        ])->assertOk();
    }

    // ---------- Student ----------

    public function test_expired_student_temp_password_is_rejected_at_login(): void
    {
        Student::factory()->create([
            'student_id'               => '9999999',
            'password'                 => Hash::make('TempPass1!'),
            'temp_password'            => Hash::make('TempPass1!'),
            'must_change_password'     => true,
            'temp_password_expires_at' => now()->subDay(),
            'is_active'                => true,
        ]);

        $this->postJson('/api/student/login', [
            'student_id'       => '9999999',
            'password'         => 'TempPass1!',
            'consent_accepted' => true,
        ])->assertStatus(401)
          ->assertJsonPath('error', 'otp_expired');
    }

    public function test_valid_student_temp_password_is_accepted(): void
    {
        Student::factory()->create([
            'student_id'               => '9999998',
            'password'                 => Hash::make('TempPass1!'),
            'temp_password'            => Hash::make('TempPass1!'),
            'must_change_password'     => true,
            'temp_password_expires_at' => now()->addDay(),
            'is_active'                => true,
        ]);

        $this->postJson('/api/student/login', [
            'student_id'       => '9999998',
            'password'         => 'TempPass1!',
            'consent_accepted' => true,
        ])->assertOk();
    }

    public function test_leftover_expiry_does_not_lock_student_who_changed_their_password(): void
    {
        Student::factory()->create([
            'student_id'               => '9999997',
            'password'                 => Hash::make('MyOwnPass1!'),
            'temp_password'            => null,
            'must_change_password'     => false,
            'temp_password_expires_at' => now()->subDay(),
            'is_active'                => true,
        ]);

        $this->postJson('/api/student/login', [
            'student_id'       => '9999997',
            'password'         => 'MyOwnPass1!',
            'consent_accepted' => true,
        ])->assertOk();
    }
}