<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

// B638: the Add/Edit Faculty form checks Employee ID and name for a
// duplicate live, as the Dean/Dept Chair types (via /faculty/check-duplicate),
// and the same checks are enforced again on submit so they can't be bypassed.
class FacultyDuplicateValidationTest extends TestCase
{
    use RefreshDatabase;

    private function dean(string $college = 'College of Information Sciences'): User
    {
        return User::factory()->create(['role' => 'dean', 'college' => $college]);
    }

    private function basePayload(array $over = []): array
    {
        return array_merge([
            'first_name' => 'Maria', 'last_name' => 'Santos', 'email' => 'maria.santos@bsu.edu.ph',
            'department' => 'Information Technology',
        ], $over);
    }

    public function test_check_duplicate_flags_an_existing_employee_id(): void
    {
        $dean = $this->dean();
        User::factory()->create(['role' => 'faculty', 'college' => $dean->college, 'employee_id' => '12345']);
        Sanctum::actingAs($dean);

        $res = $this->getJson('/api/faculty/check-duplicate?employee_id=12345')->assertOk();
        $this->assertTrue($res->json('employee_id_exists'));
        $this->assertFalse($res->json('name_exists'));
    }

    public function test_check_duplicate_flags_an_existing_name_in_the_same_scope(): void
    {
        $dean = $this->dean();
        User::factory()->create(['role' => 'faculty', 'college' => $dean->college, 'first_name' => 'Juan', 'last_name' => 'Cruz']);
        Sanctum::actingAs($dean);

        $res = $this->getJson('/api/faculty/check-duplicate?first_name=juan&last_name=CRUZ')->assertOk();
        $this->assertTrue($res->json('name_exists'));
        $this->assertFalse($res->json('employee_id_exists'));
    }

    public function test_check_duplicate_ignores_the_record_being_edited(): void
    {
        $dean = $this->dean();
        $existing = User::factory()->create(['role' => 'faculty', 'college' => $dean->college, 'employee_id' => '777', 'first_name' => 'Ana', 'last_name' => 'Reyes']);
        Sanctum::actingAs($dean);

        $res = $this->getJson("/api/faculty/check-duplicate?employee_id=777&first_name=Ana&last_name=Reyes&ignore_id={$existing->id}")->assertOk();
        $this->assertFalse($res->json('employee_id_exists'));
        $this->assertFalse($res->json('name_exists'));
    }

    public function test_check_duplicate_does_not_see_another_colleges_name(): void
    {
        $dean = $this->dean('College of Information Sciences');
        User::factory()->create(['role' => 'faculty', 'college' => 'College of Business', 'first_name' => 'Juan', 'last_name' => 'Cruz']);
        Sanctum::actingAs($dean);

        $res = $this->getJson('/api/faculty/check-duplicate?first_name=Juan&last_name=Cruz')->assertOk();
        $this->assertFalse($res->json('name_exists'));
    }

    public function test_store_rejects_a_duplicate_employee_id(): void
    {
        $dean = $this->dean();
        User::factory()->create(['role' => 'faculty', 'college' => $dean->college, 'employee_id' => '555']);
        Sanctum::actingAs($dean);

        $this->postJson('/api/faculty', $this->basePayload(['employee_id' => '555']))
            ->assertStatus(422)->assertJsonValidationErrors('employee_id');
    }

    public function test_store_rejects_a_duplicate_name_in_scope(): void
    {
        $dean = $this->dean();
        User::factory()->create(['role' => 'faculty', 'college' => $dean->college, 'first_name' => 'Maria', 'last_name' => 'Santos']);
        Sanctum::actingAs($dean);

        $this->postJson('/api/faculty', $this->basePayload())
            ->assertStatus(422)->assertJsonValidationErrors('last_name');
    }

    public function test_update_allows_saving_the_same_record_unchanged(): void
    {
        $dean = $this->dean();
        $faculty = User::factory()->create([
            'role' => 'faculty', 'college' => $dean->college, 'department' => 'Information Technology',
            'employee_id' => '999', 'first_name' => 'Maria', 'last_name' => 'Santos',
        ]);
        Sanctum::actingAs($dean);

        $this->putJson("/api/faculty/{$faculty->id}", [
            'first_name' => 'Maria', 'last_name' => 'Santos', 'employee_id' => '999',
        ])->assertOk();
    }
}