<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\User;
use App\Support\PasswordReset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * B643 (10/31/2026): admin-initiated resets used to leave the plaintext
 * temp password retrievable forever via viewTempPassword() and left
 * temp_password_expires_at NULL. Both are fixed - these tests lock in:
 *   - no plaintext is ever stored
 *   - viewTempPassword() never leaks a value
 *   - a temp password carries a real expiry
 *   - reset revokes active sessions
 */
class TempPasswordExpiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_reset_stores_only_a_hash_of_the_temp_password(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $student = Student::factory()->create(['email' => 'stu@bsu.edu.ph', 'must_change_password' => false]);
        Sanctum::actingAs($admin);

        $response = $this->postJson("/api/students/{$student->id}/reset-password", [
            'reason' => 'no_email_access',
            'note'   => 'Verified in person.',
        ])->assertOk();

        $plain = $response->json('temp_password');
        $this->assertIsString($plain);
        $this->assertSame(10, strlen($plain), 'Temp password must be the generated 10-char value.');

        $fresh = $student->fresh();
        // Stored value is a hash, NOT the plaintext.
        $this->assertNotSame($plain, $fresh->temp_password);
        $this->assertTrue(Hash::check($plain, $fresh->temp_password));
        // Both password columns are hashed, and the account must change on next login.
        $this->assertTrue(Hash::check($plain, $fresh->password));
        $this->assertTrue((bool) $fresh->must_change_password);
        // Real expiry is set (not null).
        $this->assertNotNull($fresh->temp_password_expires_at);
        $this->assertTrue($fresh->temp_password_expires_at->isFuture());
    }

    public function test_admin_reset_revokes_active_sessions(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $student = Student::factory()->create();
        $student->createToken('mobile');
        $this->assertSame(1, $student->tokens()->count());

        Sanctum::actingAs($admin);
        $this->postJson("/api/students/{$student->id}/reset-password", [
            'reason' => 'no_email_access',
        ])->assertOk();

        $this->assertSame(0, $student->fresh()->tokens()->count(), 'Reset must revoke every active session.');
    }

    public function test_view_temp_password_never_returns_the_value(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $student = Student::factory()->create(['must_change_password' => true, 'temp_password' => Hash::make('abcdef1234')]);
        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/students/{$student->id}/temp-password")->assertOk();
        $response->assertJsonMissing(['temp_password' => 'abcdef1234']);
        $this->assertTrue((bool) $response->json('pending'));
        $this->assertStringContainsString('shown once', strtolower($response->json('message')));
    }

    public function test_view_temp_password_404s_once_student_has_set_their_own(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $student = Student::factory()->create(['must_change_password' => false, 'temp_password' => null]);
        Sanctum::actingAs($admin);

        $this->getJson("/api/students/{$student->id}/temp-password")->assertStatus(404);
    }

    public function test_password_reset_via_email_otp_clears_temp_state(): void
    {
        $student = Student::factory()->create([
            'email'                 => 'stu@bsu.edu.ph',
            'must_change_password'  => true,
            'temp_password'         => Hash::make('old'),
            'temp_password_expires_at' => now()->addDays(3),
        ]);
        $student->createToken('mobile');

        // Simulate PasswordResetController::reset() with a valid OTP.
        \Illuminate\Support\Facades\DB::table('password_reset_otps')->insert([
            'type'       => 'student',
            'email'      => 'stu@bsu.edu.ph',
            'code_hash'  => Hash::make('123456'),
            'attempts'   => 0,
            'expires_at' => now()->addMinutes(10),
            'sent_at'    => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson('/api/reset-password', [
            'type'     => 'student',
            'email'    => 'stu@bsu.edu.ph',
            'otp'      => '123456',
            'password' => 'Brand!New9Pass',
        ])->assertOk();

        $fresh = $student->fresh();
        $this->assertNull($fresh->temp_password);
        $this->assertNull($fresh->temp_password_expires_at);
        $this->assertFalse((bool) $fresh->must_change_password);
        $this->assertSame(0, $fresh->tokens()->count(), 'Real reset must also revoke sessions.');
    }
}