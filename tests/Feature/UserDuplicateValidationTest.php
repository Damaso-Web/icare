<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

// B#, 11/4/2026: the Add/Edit Employee form (User Management) now checks
// Employee ID, Name, and Email for a duplicate live, as the admin types
// (via /users/check-duplicate) - it never checked any of these live
// before (Employee ID and Email were only ever caught at submit; Name
// wasn't checked at all). Mirrors FacultyDuplicateValidationTest.
class UserDuplicateValidationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_check_duplicate_flags_an_existing_employee_id(): void
    {
        Sanctum::actingAs($this->admin());
        User::factory()->create(['role' => 'gcu_staff', 'employee_id' => '12345']);

        $res = $this->getJson('/api/users/check-duplicate?employee_id=12345')->assertOk();
        $this->assertTrue($res->json('employee_id_exists'));
        $this->assertFalse($res->json('name_exists'));
        $this->assertFalse($res->json('email_exists'));
    }

    public function test_check_duplicate_flags_an_existing_name(): void
    {
        Sanctum::actingAs($this->admin());
        User::factory()->create(['role' => 'gcu_staff', 'first_name' => 'Juan', 'last_name' => 'Cruz']);

        $res = $this->getJson('/api/users/check-duplicate?first_name=juan&last_name=CRUZ')->assertOk();
        $this->assertTrue($res->json('name_exists'));
    }

    public function test_check_duplicate_treats_a_different_middle_name_as_not_a_match(): void
    {
        Sanctum::actingAs($this->admin());
        User::factory()->create(['role' => 'gcu_staff', 'first_name' => 'Juan', 'middle_name' => 'Garcia', 'last_name' => 'Cruz']);

        $res = $this->getJson('/api/users/check-duplicate?first_name=Juan&last_name=Cruz&middle_name=Reyes')->assertOk();
        $this->assertFalse($res->json('name_exists'));

        $res = $this->getJson('/api/users/check-duplicate?first_name=Juan&last_name=Cruz&middle_name=garcia')->assertOk();
        $this->assertTrue($res->json('name_exists'));
    }

    public function test_check_duplicate_flags_an_existing_email(): void
    {
        Sanctum::actingAs($this->admin());
        User::factory()->create(['role' => 'gcu_staff', 'email' => 'maria.santos@bsu.edu.ph']);

        $res = $this->getJson('/api/users/check-duplicate?email=MARIA.SANTOS@bsu.edu.ph')->assertOk();
        $this->assertTrue($res->json('email_exists'));
    }

    public function test_check_duplicate_ignores_the_record_being_edited(): void
    {
        Sanctum::actingAs($this->admin());
        $existing = User::factory()->create([
            'role' => 'gcu_staff', 'employee_id' => '777', 'first_name' => 'Ana', 'last_name' => 'Reyes', 'email' => 'ana.reyes@bsu.edu.ph',
        ]);

        $res = $this->getJson("/api/users/check-duplicate?employee_id=777&first_name=Ana&last_name=Reyes&email=ana.reyes@bsu.edu.ph&ignore_id={$existing->id}")->assertOk();
        $this->assertFalse($res->json('employee_id_exists'));
        $this->assertFalse($res->json('name_exists'));
        $this->assertFalse($res->json('email_exists'));
    }

    // The route must be declared before apiResource('users', ...) or it's
    // swallowed by the {user} show route and 404s with "check-duplicate"
    // bound as the id (same bug class as B648's appointments/availability).
    public function test_check_duplicate_route_is_not_swallowed_by_the_users_show_route(): void
    {
        Sanctum::actingAs($this->admin());

        $this->getJson('/api/users/check-duplicate?employee_id=99999')->assertOk();
    }
}