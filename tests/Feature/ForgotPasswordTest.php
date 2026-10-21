<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\User;
use App\Notifications\PasswordResetOtpNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

    private const NEW_PASSWORD = 'NewPass@2026x';

    /** Requests a code and captures the 6 digits that were emailed. */
    private function requestCode(string $email, string $type, $account): string
    {
        $code = null;
        $this->postJson('/api/forgot-password', ['email' => $email, 'type' => $type])->assertOk();
        Notification::assertSentTo($account, PasswordResetOtpNotification::class, function ($n) use (&$code) {
            $code = (fn () => $this->otp)->call($n);
            return true;
        });
        return $code;
    }

    public function test_staff_email_must_be_bsu_domain(): void
    {
        User::factory()->create(['email' => 'someone@gmail.com', 'role' => 'admin', 'is_active' => true]);
        Notification::fake();
        $this->postJson('/api/forgot-password', ['email' => 'someone@gmail.com', 'type' => 'staff'])
            ->assertStatus(422)->assertJsonValidationErrors('email');
        Notification::assertNothingSent();
    }

    public function test_unknown_staff_email_is_rejected(): void
    {
        Notification::fake();
        $this->postJson('/api/forgot-password', ['email' => 'nobody@bsu.edu.ph', 'type' => 'staff'])
            ->assertStatus(422)->assertJsonValidationErrors('email');
        Notification::assertNothingSent();
    }

    public function test_unknown_student_email_is_rejected(): void
    {
        $this->postJson('/api/forgot-password', ['email' => 'nobody@gmail.com', 'type' => 'student'])
            ->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_staff_gets_a_six_digit_code_not_a_link_and_can_reset(): void
    {
        Notification::fake();
        $u = User::factory()->create(['email' => 'staff@bsu.edu.ph', 'role' => 'admin', 'is_active' => true, 'must_change_password' => true]);
        $u->createToken('t');

        $code = $this->requestCode('staff@bsu.edu.ph', 'staff', $u);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        // Only a hash of the code is stored.
        $this->assertNotSame($code, DB::table('password_reset_otps')->value('code_hash'));

        $this->postJson('/api/reset-password', [
            'email' => 'staff@bsu.edu.ph', 'type' => 'staff', 'otp' => $code,
            'password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD,
        ])->assertOk();

        $u->refresh();
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $u->password));
        $this->assertFalse($u->must_change_password);
        $this->assertSame('email_otp', $u->password_change_source);
        $this->assertSame(0, $u->tokens()->count());
        $this->assertSame(0, DB::table('password_reset_otps')->count());

        // A code works once.
        $this->postJson('/api/reset-password', [
            'email' => 'staff@bsu.edu.ph', 'type' => 'staff', 'otp' => $code,
            'password' => 'Another@2026pw', 'password_confirmation' => 'Another@2026pw',
        ])->assertStatus(422)->assertJsonValidationErrors('otp');
    }

    public function test_wrong_code_is_counted_and_five_misses_cancel_it(): void
    {
        Notification::fake();
        // The route also has a per-minute request limit; here we test the code's own 5-miss limit.
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        $u = User::factory()->create(['email' => 'staff@bsu.edu.ph', 'role' => 'admin', 'is_active' => true]);
        $code = $this->requestCode('staff@bsu.edu.ph', 'staff', $u);
        $wrong = $code === '000000' ? '111111' : '000000';
        $body = ['email' => 'staff@bsu.edu.ph', 'type' => 'staff', 'password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD];

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/reset-password', $body + ['otp' => $wrong])->assertStatus(422)->assertJsonValidationErrors('otp');
        }
        // The right code no longer works: the code was cancelled.
        $this->postJson('/api/reset-password', $body + ['otp' => $code])->assertStatus(422);
        $this->assertFalse(Hash::check(self::NEW_PASSWORD, $u->fresh()->password));
    }

    public function test_expired_code_is_refused_and_resend_is_limited(): void
    {
        Notification::fake();
        $u = User::factory()->create(['email' => 'staff@bsu.edu.ph', 'role' => 'admin', 'is_active' => true]);
        $code = $this->requestCode('staff@bsu.edu.ph', 'staff', $u);

        // Asking again straight away is refused.
        $this->postJson('/api/forgot-password', ['email' => 'staff@bsu.edu.ph', 'type' => 'staff'])
            ->assertStatus(422)->assertJsonValidationErrors('email');

        $this->travel(11)->minutes();
        $this->postJson('/api/reset-password', [
            'email' => 'staff@bsu.edu.ph', 'type' => 'staff', 'otp' => $code,
            'password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD,
        ])->assertStatus(422)->assertJsonValidationErrors('otp');
    }

    public function test_student_can_reset_with_a_code_and_the_new_password_is_strong_enough(): void
    {
        Notification::fake();
        $s = Student::factory()->create(['email' => 'stu@example.com', 'is_active' => true]);
        $code = $this->requestCode('stu@example.com', 'student', $s);

        $this->postJson('/api/reset-password', [
            'email' => 'stu@example.com', 'type' => 'student', 'otp' => $code,
            'password' => 'weak', 'password_confirmation' => 'weak',
        ])->assertStatus(422)->assertJsonValidationErrors('password');

        $this->postJson('/api/reset-password', [
            'email' => 'stu@example.com', 'type' => 'student', 'otp' => $code,
            'password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD,
        ])->assertOk();
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $s->fresh()->password));
    }

    public function test_a_code_for_one_account_does_not_reset_another(): void
    {
        Notification::fake();
        $a = User::factory()->create(['email' => 'a@bsu.edu.ph', 'role' => 'admin', 'is_active' => true]);
        $b = User::factory()->create(['email' => 'b@bsu.edu.ph', 'role' => 'admin', 'is_active' => true]);
        $codeA = $this->requestCode('a@bsu.edu.ph', 'staff', $a);

        $this->postJson('/api/reset-password', [
            'email' => 'b@bsu.edu.ph', 'type' => 'staff', 'otp' => $codeA,
            'password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD,
        ])->assertStatus(422);
        $this->assertFalse(Hash::check(self::NEW_PASSWORD, $b->fresh()->password));
    }
}