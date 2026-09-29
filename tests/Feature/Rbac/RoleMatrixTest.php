<?php

namespace Tests\Feature\Rbac;

use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleMatrixTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        return User::factory()->create([
            'role'      => $role,
            'is_active' => true,
            'password'  => bcrypt('password'),
        ]);
    }

    public static function adminOnlyRoutes(): array
    {
        return [
            'list users'      => ['get', '/api/users'],
            'view audit logs' => ['get', '/api/audit-logs'],
            'list backups'    => ['get', '/api/backups'],
        ];
    }

    /** @dataProvider adminOnlyRoutes */
    public function test_admin_can_access_admin_routes(string $method, string $uri): void
    {
        $this->actingAs($this->userWithRole('admin'))->json($method, $uri)->assertStatus(200);
    }

    /** @dataProvider adminOnlyRoutes */
    public function test_faculty_forbidden_on_admin_routes(string $method, string $uri): void
    {
        $this->actingAs($this->userWithRole('faculty'))->json($method, $uri)->assertStatus(403);
    }

    /** @dataProvider adminOnlyRoutes */
    public function test_unauthenticated_gets_401_not_403(string $method, string $uri): void
    {
        $this->json($method, $uri)->assertStatus(401);
    }

    public function test_admin_cannot_create_system_admin(): void
    {
        $this->actingAs($this->userWithRole('admin'))
            ->postJson('/api/users', [
                'first_name'            => 'New',
                'last_name'             => 'SysAdmin',
                'email'                 => 'new-sys@bsu.edu.ph',
                'role'                  => 'system_admin',
                'password'              => 'Password1!',
                'password_confirmation' => 'Password1!',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('role');
    }

    public function test_system_admin_can_create_system_admin(): void
    {
        $this->actingAs($this->userWithRole('system_admin'))
            ->postJson('/api/users', [
                'first_name'            => 'Another',
                'last_name'             => 'SysAdmin',
                'email'                 => 'another-sys@bsu.edu.ph',
                'role'                  => 'system_admin',
                'password'              => 'Password1!',
                'password_confirmation' => 'Password1!',
            ])
            ->assertStatus(201);
    }

    public function test_admin_cannot_change_own_role(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
             ->putJson("/api/users/{$admin->id}", ['role' => 'faculty'])
             ->assertStatus(200);

        $this->assertSame('admin', $admin->fresh()->role);
    }

    public function test_faculty_cannot_create_session_note(): void
    {
        $case = CaseFile::factory()->create();

        $this->actingAs($this->userWithRole('faculty'))
             ->postJson("/api/cases/{$case->id}/session-notes", [])
             ->assertStatus(403);
    }

    public function test_gcu_staff_can_create_session_note(): void
    {
        $case = CaseFile::factory()->create();

        $this->actingAs($this->userWithRole('gcu_staff'))
             ->postJson("/api/cases/{$case->id}/session-notes", [
                 'session_date' => now()->toDateString(),
                 'session_type' => 'initial',
                 'observations' => 'Test observation.',
             ])
             ->assertStatus(201);
    }

    public function test_gcu_staff_cannot_record_sanction(): void
    {
        $case = CaseFile::factory()->create();

        $this->actingAs($this->userWithRole('gcu_staff'))
             ->postJson("/api/cases/{$case->id}/interventions", [
                 'type'        => 'sanction',
                 'description' => 'Test sanction.',
             ])
             ->assertStatus(403);
    }

    public function test_sdu_head_can_record_sanction(): void
    {
        $case = CaseFile::factory()->create();

        $this->actingAs($this->userWithRole('sdu_head'))
             ->postJson("/api/cases/{$case->id}/interventions", [
                 'type'        => 'sanction',
                 'description' => 'Test sanction.',
             ])
             ->assertStatus(201);
    }

    public function test_sdu_head_cannot_record_non_sanction(): void
    {
        $case = CaseFile::factory()->create();

        $this->actingAs($this->userWithRole('sdu_head'))
             ->postJson("/api/cases/{$case->id}/interventions", [
                 'type'        => 'follow_up',
                 'description' => 'Test follow-up.',
             ])
             ->assertStatus(403);
    }

    public function test_tmdu_staff_cannot_record_any_intervention(): void
    {
        $case = CaseFile::factory()->create();

        $this->actingAs($this->userWithRole('tmdu_staff'))
             ->postJson("/api/cases/{$case->id}/interventions", [
                 'type'        => 'sanction',
                 'description' => 'Test.',
             ])
             ->assertStatus(403);
    }

    public function test_tmdu_sees_only_testing_referrals_on_tmdu_cases(): void
    {
        $tmdu = $this->userWithRole('tmdu_staff');

        $gcuCase  = CaseFile::factory()->create(['current_unit' => 'GCU']);
        $tmduCase = CaseFile::factory()->create(['current_unit' => 'TMDU']);

        $notYet    = Referral::factory()->create(['case_id' => $gcuCase->id,  'referral_type' => 'psychological_testing']);
        $yours     = Referral::factory()->create(['case_id' => $tmduCase->id, 'referral_type' => 'psychological_testing']);
        $wrongType = Referral::factory()->create(['case_id' => $tmduCase->id, 'referral_type' => 'counseling']);

        $ids = collect(
            $this->actingAs($tmdu)->getJson('/api/referrals')->json('data')
        )->pluck('id');

        $this->assertTrue($ids->contains($yours->id));
        $this->assertFalse($ids->contains($notYet->id));
        $this->assertFalse($ids->contains($wrongType->id));
    }

    public function test_tmdu_cannot_write_to_testing_referral_owned_by_gcu(): void
    {
        $tmdu = $this->userWithRole('tmdu_staff');
        $ref  = Referral::factory()->create([
            'case_id'       => CaseFile::factory()->create(['current_unit' => 'GCU'])->id,
            'referral_type' => 'psychological_testing',
        ]);

        $this->actingAs($tmdu)
             ->patchJson("/api/referrals/{$ref->id}/status", ['status' => 'acknowledged'])
             ->assertStatus(403);
    }

    public function test_tmdu_cannot_open_non_testing_referral_by_id(): void
    {
        $tmdu = $this->userWithRole('tmdu_staff');
        $ref  = Referral::factory()->create(['referral_type' => 'counseling']);

        $this->actingAs($tmdu)->getJson("/api/referrals/{$ref->id}")->assertStatus(403);
    }

    public function test_faculty_only_sees_own_archived_referrals(): void
    {
        $facultyA = $this->userWithRole('faculty');
        $facultyB = $this->userWithRole('faculty');

        $mine    = Referral::factory()->create(['referred_by_user_id' => $facultyA->id, 'is_archived' => true]);
        $notMine = Referral::factory()->create(['referred_by_user_id' => $facultyB->id, 'is_archived' => true]);

        $ids = collect(
            $this->actingAs($facultyA)->getJson('/api/referrals-archived')->json('data')
        )->pluck('id');

        $this->assertTrue($ids->contains($mine->id));
        $this->assertFalse($ids->contains($notMine->id));
    }
}