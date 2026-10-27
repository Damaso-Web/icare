<?php

namespace Tests\Feature;

use App\Models\College;
use App\Models\Department;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use App\Support\DepartmentScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

// Management > Programs can now link a program to its department (10/27/2026)
// instead of the two lists only ever being connected by a name guess.
class ProgramDepartmentLinkTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $u = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        Sanctum::actingAs($u);
        return $u;
    }

    public function test_program_can_be_linked_to_a_department_in_the_same_college(): void
    {
        $this->admin();
        $college = College::create(['name' => 'College of Testing', 'abbrev' => 'CT']);
        $dept = Department::create(['college_id' => $college->id, 'name' => 'Information Technology']);

        $res = $this->postJson('/api/management/programs', [
            'college_id' => $college->id, 'department_id' => $dept->id,
            'name' => 'BS in a Completely Different Name',
        ])->assertCreated();

        $this->assertSame($dept->id, $res->json('department_id'));
        $this->assertSame('Information Technology', $res->json('department.name'));
    }

    public function test_program_cannot_be_linked_to_a_department_from_another_college(): void
    {
        $this->admin();
        $collegeA = College::create(['name' => 'College A', 'abbrev' => 'CA']);
        $collegeB = College::create(['name' => 'College B', 'abbrev' => 'CB']);
        $deptB = Department::create(['college_id' => $collegeB->id, 'name' => 'Some Department']);

        $this->postJson('/api/management/programs', [
            'college_id' => $collegeA->id, 'department_id' => $deptB->id, 'name' => 'A Program',
        ])->assertStatus(422)->assertJsonValidationErrors(['department_id']);
    }

    public function test_program_without_a_department_still_saves(): void
    {
        $this->admin();
        $college = College::create(['name' => 'College of Testing', 'abbrev' => 'CT']);

        $res = $this->postJson('/api/management/programs', [
            'college_id' => $college->id, 'name' => 'Unlinked Program',
        ])->assertCreated();

        $this->assertNull($res->json('department_id'));
    }

    public function test_department_scope_prefers_the_explicit_link_over_the_name_guess(): void
    {
        $college = College::create(['name' => 'College of Testing', 'abbrev' => 'CT']);
        $dept = Department::create(['college_id' => $college->id, 'name' => 'Computing']);
        // The name alone would never match "Computing" by substring, but the
        // explicit link makes it match.
        Program::create(['college_id' => $college->id, 'department_id' => $dept->id, 'name' => 'BS in Information Technology']);

        $this->assertTrue(DepartmentScope::matches('BS in Information Technology', 'Computing'));
        $this->assertFalse(DepartmentScope::matches('BS in Information Technology', 'Some Other Department'));
    }

    public function test_department_scope_falls_back_to_name_guess_when_unlinked(): void
    {
        // No Program row at all for this name - same behavior as before the link existed.
        $this->assertTrue(DepartmentScope::matches('Bachelor of Science in Information Technology', 'Information Technology'));
        $this->assertFalse(DepartmentScope::matches('Bachelor of Science in Information Technology', 'Nursing'));
    }

    public function test_where_program_matches_includes_linked_programs_whose_name_would_not_otherwise_match(): void
    {
        $college = College::create(['name' => 'College of Testing', 'abbrev' => 'CT']);
        $dept = Department::create(['college_id' => $college->id, 'name' => 'Computing']);
        Program::create(['college_id' => $college->id, 'department_id' => $dept->id, 'name' => 'BS in Information Technology']);

        Student::create([
            'student_id' => '3000001', 'first_name' => 'A', 'last_name' => 'B', 'year_level' => '1st Year',
            'college' => $college->name, 'program' => 'BS in Information Technology',
            'password' => bcrypt('x'), 'is_active' => true,
        ]);

        $count = Student::query()->where(fn ($q) => DepartmentScope::whereProgramMatches($q, 'Computing'))->count();
        $this->assertSame(1, $count);
    }
}