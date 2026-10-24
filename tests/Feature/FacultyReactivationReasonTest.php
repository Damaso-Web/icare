<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

// Bug (10/14/2026): the Faculty page's Reactivate Account action let a Dean
// or Dept Chair pick any reactivation reason, with no relation to how the
// account was actually deactivated (e.g. "Resigned" reactivated as
// "Security Verified"). FacultyController::toggle() now applies the same
// EmployeeAccountReasons::allowedReactivationKeys() restriction User
// Management's UserController::toggleActive() already enforces.
class FacultyReactivationReasonTest extends TestCase
{
    use RefreshDatabase;

    private function dean(): User
    {
        return User::factory()->create(['role' => 'dean', 'college' => 'College of Information Sciences']);
    }

    public function test_reactivation_reason_must_match_the_original_deactivation_reason(): void
    {
        $dean = $this->dean();
        Sanctum::actingAs($dean);
        $faculty = User::factory()->create(['role' => 'faculty', 'college' => $dean->college, 'is_active' => true]);

        $this->postJson("/api/faculty/{$faculty->id}/toggle-active", ['reason' => 'resigned'])->assertOk();
        $faculty->refresh();
        $this->assertSame('Resigned', $faculty->deactivation_reason);

        // "Security Verified" has nothing to do with "Resigned".
        $this->postJson("/api/faculty/{$faculty->id}/toggle-active", ['reason' => 'security_verified'])
            ->assertStatus(422)->assertJsonValidationErrors('reason');

        // The matching reason, and the always-available escape hatches, are accepted.
        $this->postJson("/api/faculty/{$faculty->id}/toggle-active", ['reason' => 'rehired'])->assertOk();
        $this->assertTrue($faculty->refresh()->is_active);
    }

    public function test_reactivation_error_and_other_escape_hatches_always_allowed(): void
    {
        $dean = $this->dean();
        Sanctum::actingAs($dean);
        $faculty = User::factory()->create(['role' => 'faculty', 'college' => $dean->college, 'is_active' => true]);

        $this->postJson("/api/faculty/{$faculty->id}/toggle-active", ['reason' => 'security'])->assertOk();
        $this->postJson("/api/faculty/{$faculty->id}/toggle-active", ['reason' => 'error'])->assertOk();
        $this->assertTrue($faculty->refresh()->is_active);
    }
}