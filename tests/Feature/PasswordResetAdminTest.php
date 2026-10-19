<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PasswordResetAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_reset_needs_allowed_reason_and_does_everything(): void
    {
        Notification::fake();
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $s = Student::factory()->create(['email' => 'stu@example.com', 'is_active' => true, 'must_change_password' => false]);
        $s->createToken('t');

        $this->postJson("/api/students/{$s->id}/reset-password", [])->assertStatus(422);
        // an employee reason is not valid for a student
        $this->postJson("/api/students/{$s->id}/reset-password", ['reason' => 'locked_out'])->assertStatus(422);
        $this->postJson("/api/students/{$s->id}/reset-password", ['reason' => 'other'])->assertStatus(422);
        $this->assertSame(1, $s->tokens()->count());

        $this->postJson("/api/students/{$s->id}/reset-password", ['reason' => 'link_not_working'])
            ->assertOk()->assertJsonStructure(['temp_password']);

        $s->refresh();
        $this->assertTrue($s->must_change_password);
        $this->assertSame(0, $s->tokens()->count());
        $this->assertSame('admin_reset', $s->password_change_source);
        Notification::assertSentTo($s, \App\Notifications\AdminPasswordResetNotification::class);
        $this->assertTrue(AuditLog::where('action', 'password_reset')->where('description', 'like', '%Reset link is not working%')->exists());

        $info = $this->getJson("/api/students/{$s->id}/password-info")->assertOk()->json();
        $this->assertTrue($info['within_hour']);
    }

    public function test_employee_reset_uses_employee_reasons(): void
    {
        Notification::fake();
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $u = User::factory()->create(['role' => 'gcu_staff', 'is_active' => true]);
        $this->postJson("/api/users/{$u->id}/reset-password", ['reason' => 'no_email_access'])->assertStatus(422);
        $this->postJson("/api/users/{$u->id}/reset-password", ['reason' => 'locked_out'])->assertOk();
        $this->assertTrue($u->fresh()->must_change_password);
    }

    public function test_reactivation_forces_password_change_and_api_is_blocked_until_changed(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $u = User::factory()->create(['role' => 'gcu_staff', 'is_active' => false, 'must_change_password' => false]);
        $this->postJson("/api/users/{$u->id}/toggle-active", ['reason' => 'error'])->assertOk();
        $this->assertTrue($u->fresh()->must_change_password);

        $s = Student::factory()->create(['is_active' => false, 'must_change_password' => false]);
        $this->postJson("/api/students/{$s->id}/toggle-active", ['reason' => 'error'])->assertOk();
        $this->assertTrue($s->fresh()->must_change_password);

        // that employee can no longer use the API except to change the password
        Sanctum::actingAs($u->fresh());
        $this->getJson('/api/dashboard')->assertStatus(403)->assertJsonPath('code', 'password_change_required');
        $this->getJson('/api/me')->assertOk();
    }
}