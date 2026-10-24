<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

// Bug (10/14/2026): My Account's email field only checked generic email
// format, not the @bsu.edu.ph institutional domain, for any staff role
// (Dept Chair included). Covers both the live-validation rule's backend
// counterpart and the UniqueAccountEmail/current-password flow staying intact.
class StaffProfileEmailDomainTest extends TestCase
{
    use RefreshDatabase;

    private function chair(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'role' => 'dept_chair', 'first_name' => 'Juan', 'last_name' => 'Cruz',
            'email' => 'chair@bsu.edu.ph',
        ], $overrides));
    }

    public function test_non_bsu_email_is_rejected_on_profile_update(): void
    {
        $user = $this->chair();
        Sanctum::actingAs($user);

        $this->putJson('/api/me/profile', [
            'first_name' => $user->first_name, 'last_name' => $user->last_name,
            'email' => 'maria.delacruz@university.eduph',
        ])->assertStatus(422)->assertJsonValidationErrors('email');

        $this->assertSame('chair@bsu.edu.ph', $user->refresh()->email);
    }

    public function test_bsu_email_is_accepted_case_insensitively(): void
    {
        $user = $this->chair(['password' => bcrypt('secret123')]);
        Sanctum::actingAs($user);

        $this->putJson('/api/me/profile', [
            'first_name' => $user->first_name, 'last_name' => $user->last_name,
            'email' => 'CHAIR.NEW@BSU.EDU.PH', 'current_password' => 'secret123',
        ])->assertOk();

        $this->assertSame('CHAIR.NEW@BSU.EDU.PH', $user->refresh()->email);
    }
}