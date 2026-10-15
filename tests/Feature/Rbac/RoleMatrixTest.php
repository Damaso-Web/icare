<?php

namespace Tests\Feature\Rbac;

use App\Models\CaseFile;
use App\Models\Appointment;
use App\Models\Referral;
use App\Models\TestingRecord;
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

    // System Admin works on Management only; User Management is for the GCU
    // Head, TMDU Head and Dean (routes/api.php has never let it in).
    public function test_system_admin_has_no_user_management(): void
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
            ->assertStatus(403);
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

    // TMDU's queue holds only its own Case Referral Slips: referrals that carry
    // a TestingRecord (made by "Refer to TMDU"). A psychological_testing
    // referral without one is still GCU's.
    public function test_tmdu_sees_only_its_case_referral_slips(): void
    {
        $tmdu = $this->userWithRole('tmdu_staff');

        $gcuCase  = CaseFile::factory()->create(['current_unit' => 'GCU']);
        $tmduCase = CaseFile::factory()->create(['current_unit' => 'TMDU']);

        $notYet    = Referral::factory()->create(['case_id' => $gcuCase->id,  'referral_type' => 'psychological_testing']);
        $yours     = Referral::factory()->create(['case_id' => $tmduCase->id, 'referral_type' => 'psychological_testing']);
        TestingRecord::create(['case_id' => $tmduCase->id, 'referral_id' => $yours->id, 'student_id' => $yours->student_id, 'referred_by_user_id' => $yours->referred_by_user_id, 'reason' => 'rbac check', 'status' => 'pending']);
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

    // ---- The leader's role list: TMDU Head, Dean (= Dean's Secretary), Dept Chair ----

    private function newAccount(string $role, array $extra = []): array
    {
        return [
            'first_name'            => 'New',
            'last_name'             => 'Account',
            'email'                 => uniqid('rbac-') . '@bsu.edu.ph',
            'role'                  => $role,
            'password'              => 'Password1!',
            'password_confirmation' => 'Password1!',
        ] + $extra;
    }

    public static function everyRoleTheGcuHeadAdds(): array
    {
        return [
            'GCU Staff'  => ['gcu_staff', []],
            'SDU Head'   => ['sdu_head', []],
            'TMDU Head'  => ['tmdu_head', []],
            'TMDU Staff' => ['tmdu_staff', []],
            'Dean'       => ['dean', ['college' => 'CIT']],
            'Dept Chair' => ['dept_chair', ['college' => 'CIT', 'department' => 'Information Technology']],
            'Faculty'    => ['faculty', ['college' => 'CIT', 'department' => 'Information Technology']],
        ];
    }

    /** @dataProvider everyRoleTheGcuHeadAdds */
    public function test_gcu_head_can_add_every_role(string $role, array $extra): void
    {
        $this->actingAs($this->userWithRole('admin'))
            ->postJson('/api/users', $this->newAccount($role, $extra))
            ->assertStatus(201)
            ->assertJsonPath('role', $role);
    }

    public function test_tmdu_head_manages_tmdu_staff_only(): void
    {
        $head  = $this->userWithRole('tmdu_head');
        $staff = $this->userWithRole('tmdu_staff');
        $gcu   = $this->userWithRole('gcu_staff');

        $roles = collect($this->actingAs($head)->getJson('/api/users')->assertStatus(200)->json('data'))->pluck('role')->unique()->values()->all();
        $this->assertSame(['tmdu_staff'], $roles);

        $this->actingAs($head)->postJson('/api/users', $this->newAccount('tmdu_staff'))->assertStatus(201);
        $this->actingAs($head)->postJson('/api/users', $this->newAccount('gcu_staff'))->assertStatus(422);
        $this->actingAs($head)->postJson("/api/users/{$staff->id}/toggle-active", ["reason" => "resigned"])->assertStatus(200);
        $this->actingAs($head)->putJson("/api/users/{$gcu->id}", ['first_name' => 'Changed'])->assertStatus(403);
    }

    public function test_dean_manages_own_college_faculty_and_chairs_only(): void
    {
        $dean    = User::factory()->create(['role' => 'dean', 'college' => 'CIT', 'is_active' => true]);
        $other   = User::factory()->create(['role' => 'faculty', 'college' => 'CN', 'department' => 'Nursing', 'is_active' => true]);

        $this->actingAs($dean)
            ->postJson('/api/users', $this->newAccount('faculty', ['college' => 'CN', 'department' => 'Information Technology']))
            ->assertStatus(201)
            ->assertJsonPath('college', 'CIT');   // always the Dean's own college
        $this->actingAs($dean)->postJson('/api/users', $this->newAccount('dept_chair', ['department' => 'Information Technology']))->assertStatus(201);
        $this->actingAs($dean)->postJson('/api/users', $this->newAccount('tmdu_staff'))->assertStatus(422);
        $this->actingAs($dean)->postJson("/api/users/{$other->id}/toggle-active")->assertStatus(403);
    }

    public function test_dean_secretary_account_works_as_dean(): void
    {
        $sec = User::factory()->create(['role' => 'dean_secretary', 'college' => 'CIT', 'is_active' => true]);

        $this->actingAs($sec)->getJson('/api/faculty')->assertStatus(200);
        $this->actingAs($sec)->getJson('/api/users')->assertStatus(200);
        $this->assertTrue($sec->isDean());
    }

    public static function rolesWithoutUserManagement(): array
    {
        return [['gcu_staff'], ['sdu_head'], ['tmdu_staff'], ['dept_chair'], ['faculty']];
    }

    /** @dataProvider rolesWithoutUserManagement */
    public function test_other_roles_have_no_user_management(string $role): void
    {
        $this->actingAs($this->userWithRole($role))->getJson('/api/users')->assertStatus(403);
    }

    public function test_tmdu_head_counts_as_tmdu_and_alone_overrides_tmdu_appointments(): void
    {
        $head  = $this->userWithRole('tmdu_head');
        $staff = $this->userWithRole('tmdu_staff');
        $this->assertTrue($head->isTMDUStaff());
        $this->assertTrue($head->isTMDUHead());

        $case = CaseFile::factory()->create(['current_unit' => 'TMDU']);
        $appt = Appointment::create([
            'case_id' => $case->id, 'student_id' => $case->student_id, 'unit' => 'TMDU',
            'appointment_type' => 'psychological_testing', 'appointment_date' => now()->addDay()->toDateString(),
            'start_time' => '09:00', 'end_time' => '10:00', 'status' => 'pending', 'request_status' => 'confirmed',
        ]);

        $this->actingAs($staff)->postJson("/api/appointments/{$appt->id}/override-status", ['target' => 'cancelled', 'reason' => 'rbac check'])->assertStatus(403);
        $this->actingAs($head)->postJson("/api/appointments/{$appt->id}/override-status", ['target' => 'cancelled', 'reason' => 'rbac check'])->assertStatus(200);
        $this->actingAs($head)->getJson('/api/testing-records')->assertStatus(200);
    }
}