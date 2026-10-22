<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\User;
use App\Notifications\ChangePasswordReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// B-fix: changing a temporary password used to leave the old "please change
// your password" bell reminder sitting there unread, so it kept showing up
// on every later login even though the account was no longer locked.
class ChangePasswordClearsReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_change_password_clears_the_stale_reminder(): void
    {
        $student = Student::factory()->create([
            'must_change_password' => true,
            'password' => bcrypt('TempPass1!'),
        ]);
        $student->notify(new ChangePasswordReminderNotification());
        $this->assertSame(1, $student->unreadNotifications()->count());

        $token = $student->createToken('t', ['student'])->plainTextToken;
        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/student/password', [
                'current_password' => 'TempPass1!',
                'password' => 'NewPass1!',
                'password_confirmation' => 'NewPass1!',
            ])->assertOk();

        $this->assertSame(0, $student->fresh()->unreadNotifications()
            ->where('type', ChangePasswordReminderNotification::class)->count());
    }

    public function test_staff_change_password_clears_the_stale_reminder(): void
    {
        $user = User::factory()->create([
            'must_change_password' => true,
            'password' => bcrypt('TempPass1!'),
        ]);
        $user->notify(new ChangePasswordReminderNotification());
        $this->assertSame(1, $user->unreadNotifications()->count());

        $token = $user->createToken('t')->plainTextToken;
        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/me/password', [
                'current_password' => 'TempPass1!',
                'password' => 'NewPass1!',
                'password_confirmation' => 'NewPass1!',
            ])->assertOk();

        $this->assertSame(0, $user->fresh()->unreadNotifications()
            ->where('type', ChangePasswordReminderNotification::class)->count());
    }
}