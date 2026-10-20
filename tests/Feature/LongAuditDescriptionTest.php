<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LongAuditDescriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_description_column_is_text(): void
    {
        $this->assertSame('text', Schema::getColumnType('audit_logs', 'description'));
    }

    public function test_long_audit_description_is_stored_not_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        AuditLog::record('x', str_repeat('a', 900));
        $this->assertSame(900, strlen(AuditLog::where('action', 'x')->first()->description));
        AuditLog::record('y', str_repeat('b', 9000));
        $this->assertSame(4000, strlen(AuditLog::where('action', 'y')->first()->description));
    }

    public function test_students_and_employees_can_be_reset_repeatedly_and_log_in_with_new_password(): void
    {
        Notification::fake();
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'name' => 'A Very Long Administrator Name For Testing']));
        $s = Student::factory()->create(['email' => 's@example.com', 'is_active' => true]);
        $u = User::factory()->create(['role' => 'gcu_staff', 'is_active' => true, 'email' => 'e@example.com']);

        foreach (['link_not_working', 'identity_verified', 'link_not_working'] as $r) {
            $res = $this->postJson("/api/students/{$s->id}/reset-password", ['reason' => $r, 'note' => str_repeat('n', 200)])->assertOk();
            $this->assertTrue(\Hash::check($res->json('temp_password'), $s->fresh()->password));
        }
        foreach (['locked_out', 'locked_out'] as $r) {
            $res = $this->postJson("/api/users/{$u->id}/reset-password", ['reason' => $r, 'note' => str_repeat('n', 200)])->assertOk();
            $this->assertTrue(\Hash::check($res->json('temp_password'), $u->fresh()->password));
        }
    }
}