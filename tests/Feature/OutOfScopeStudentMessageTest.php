<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

// B636: a Dean/Dept Chair/Faculty student search is scoped to their own
// college/department (B590/B618/B626) - a student who exists but sits
// outside that scope should be flagged as such ("out_of_scope"), not just
// look identical to a student who doesn't exist in the portal at all.
class OutOfScopeStudentMessageTest extends TestCase
{
    use RefreshDatabase;

    private function student(string $id, string $college, string $program = 'BSIT'): Student
    {
        return Student::create([
            'student_id' => $id, 'first_name' => 'Maria', 'last_name' => 'Santos', 'year_level' => '1st Year',
            'college' => $college, 'program' => $program, 'password' => bcrypt('x'), 'is_active' => true,
        ]);
    }

    public function test_dean_search_flags_an_out_of_scope_student_that_exists(): void
    {
        $dean = User::factory()->create(['role' => 'dean', 'college' => 'CIT']);
        $this->student('2800001', 'College of Business');
        Sanctum::actingAs($dean);

        $res = $this->getJson('/api/students?search=Santos&is_active=1')->assertOk();
        $res->assertJsonCount(0, 'data');
        $this->assertTrue($res->json('out_of_scope'));
    }

    public function test_dean_search_does_not_flag_a_student_that_truly_does_not_exist(): void
    {
        $dean = User::factory()->create(['role' => 'dean', 'college' => 'CIT']);
        Sanctum::actingAs($dean);

        $res = $this->getJson('/api/students?search=Nobody&is_active=1')->assertOk();
        $res->assertJsonCount(0, 'data');
        $this->assertFalse($res->json('out_of_scope'));
    }

    public function test_dean_search_finds_their_own_college_student_normally(): void
    {
        $dean = User::factory()->create(['role' => 'dean', 'college' => 'CIT']);
        $this->student('2800002', 'CIT');
        Sanctum::actingAs($dean);

        $res = $this->getJson('/api/students?search=Santos&is_active=1')->assertOk();
        $res->assertJsonCount(1, 'data');
        $this->assertFalse($res->json('out_of_scope'));
    }

    public function test_unscoped_role_search_never_sets_the_flag(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->student('2800003', 'College of Business');
        Sanctum::actingAs($admin);

        $res = $this->getJson('/api/students?search=Santos&is_active=1')->assertOk();
        $res->assertJsonCount(1, 'data');
        $this->assertFalse($res->json('out_of_scope'));
    }
}