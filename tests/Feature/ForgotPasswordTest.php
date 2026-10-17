<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_registered_staff_email_gets_the_link(): void
    {
        $u = User::factory()->create(['email' => 'staff@bsu.edu.ph', 'role' => 'admin', 'is_active' => true]);
        Notification::fake();
        $this->postJson('/api/forgot-password', ['email' => 'staff@bsu.edu.ph', 'type' => 'staff'])->assertOk();
        Notification::assertSentTo($u, \App\Notifications\ResetPasswordLinkNotification::class);
    }

    public function test_unknown_student_email_is_rejected(): void
    {
        $this->postJson('/api/forgot-password', ['email' => 'nobody@gmail.com', 'type' => 'student'])
            ->assertStatus(422)->assertJsonValidationErrors('email');
    }
}