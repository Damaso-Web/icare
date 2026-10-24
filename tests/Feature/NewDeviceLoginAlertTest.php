<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\User;
use App\Notifications\NewDeviceLoginNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

// B630: an account gets a notification-bell alert the first time it signs in
// from a device/browser it hasn't used before, so it finds out about a
// sign-in it didn't make. Not fired on the very first login ever (nothing to
// compare against yet), and not fired again for a device it has already used.
class NewDeviceLoginAlertTest extends TestCase
{
    use RefreshDatabase;

    private const CHROME_WINDOWS = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/118.0 Safari/537.36';
    private const SAFARI_IPHONE  = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Version/17.0 Safari/604.1';

    private function staffLogin(string $email, string $password, string $userAgent)
    {
        return $this->withHeaders(['User-Agent' => $userAgent])
            ->postJson('/api/login', ['email' => $email, 'password' => $password]);
    }

    // must_change_password defaults to true on a fresh account, which fires
    // its own unrelated ChangePasswordReminderNotification on first login -
    // turned off here so these tests only see the notification they're
    // actually about.
    public function test_first_ever_login_sends_no_alert(): void
    {
        Notification::fake();
        $user = User::factory()->create(['role' => 'gcu_staff', 'password' => Hash::make('secret123'), 'must_change_password' => false]);

        $this->staffLogin($user->email, 'secret123', self::CHROME_WINDOWS)->assertOk();

        Notification::assertNothingSent();
    }

    public function test_second_login_from_a_new_device_sends_an_alert(): void
    {
        Notification::fake();
        $user = User::factory()->create(['role' => 'gcu_staff', 'password' => Hash::make('secret123'), 'must_change_password' => false]);

        $this->staffLogin($user->email, 'secret123', self::CHROME_WINDOWS)->assertOk();
        $this->staffLogin($user->email, 'secret123', self::SAFARI_IPHONE)->assertOk();

        Notification::assertSentTo($user, NewDeviceLoginNotification::class, function ($n) {
            return $n->device === 'Safari on iPhone';
        });
    }

    public function test_repeat_login_from_an_already_seen_device_sends_no_alert(): void
    {
        Notification::fake();
        $user = User::factory()->create(['role' => 'gcu_staff', 'password' => Hash::make('secret123'), 'must_change_password' => false]);

        $this->staffLogin($user->email, 'secret123', self::CHROME_WINDOWS)->assertOk();
        $this->staffLogin($user->email, 'secret123', self::SAFARI_IPHONE)->assertOk();
        Notification::fake(); // reset the count - only care about this third login
        $this->staffLogin($user->email, 'secret123', self::CHROME_WINDOWS)->assertOk();

        Notification::assertNothingSent();
    }

    public function test_student_second_login_from_a_new_device_sends_an_alert(): void
    {
        Notification::fake();
        $student = Student::factory()->create(['password' => Hash::make('secret123'), 'must_change_password' => false]);
        $login = fn ($ua) => $this->withHeaders(['User-Agent' => $ua])->postJson('/api/student/login', [
            'student_id' => $student->student_id, 'password' => 'secret123', 'consent_accepted' => true,
        ]);

        $login(self::CHROME_WINDOWS)->assertOk();
        $login(self::SAFARI_IPHONE)->assertOk();

        Notification::assertSentTo($student, NewDeviceLoginNotification::class, function ($n) {
            return $n->device === 'Safari on iPhone';
        });
    }
}