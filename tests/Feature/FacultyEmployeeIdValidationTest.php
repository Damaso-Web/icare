<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

// B612/B613: the Add/Edit Faculty form's Employee ID is numbers-only, with
// the same 15-character limit as the other Employee ID field in the system
// (User Management's Add Employee).
class FacultyEmployeeIdValidationTest extends TestCase
{
    use RefreshDatabase;

    private function dean(): User
    {
        return User::factory()->create(['role' => 'dean', 'college' => 'College of Information Sciences']);
    }

    private function basePayload(array $over = []): array
    {
        return array_merge([
            'first_name' => 'Maria', 'last_name' => 'Santos', 'email' => 'maria.santos@bsu.edu.ph',
            'department' => 'Information Technology',
        ], $over);
    }

    public function test_employee_id_rejects_letters(): void
    {
        Sanctum::actingAs($this->dean());
        $this->postJson('/api/faculty', $this->basePayload(['employee_id' => 'ABC123']))
            ->assertStatus(422)->assertJsonValidationErrors('employee_id');
    }

    public function test_employee_id_rejects_more_than_fifteen_digits(): void
    {
        Sanctum::actingAs($this->dean());
        $this->postJson('/api/faculty', $this->basePayload(['employee_id' => '1234567890123456']))
            ->assertStatus(422)->assertJsonValidationErrors('employee_id');
    }

    public function test_employee_id_accepts_digits_within_the_limit(): void
    {
        Sanctum::actingAs($this->dean());
        $this->postJson('/api/faculty', $this->basePayload(['employee_id' => '123456789012345']))
            ->assertCreated();
    }
}