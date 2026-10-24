<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Bug (10/25/2026, B626): a plain Faculty member's "+ Refer Student" search
// could search and select any student university-wide - mirrors
// DeptChairDepartmentScopeTest (college AND program/department must match),
// one role up from the Chair.
class FacultyDepartmentScopeTest extends TestCase
{
    use RefreshDatabase;

    private function faculty(): User
    {
        return User::factory()->create([
            'role' => 'faculty',
            'college' => 'College of Information Sciences',
            'department' => 'BS Information Technology',
        ]);
    }

    private function student(string $id, string $college, string $program): Student
    {
        return Student::create([
            'student_id' => $id, 'first_name' => 'Juan', 'last_name' => 'Cruz', 'year_level' => '1st Year',
            'college' => $college, 'program' => $program, 'password' => bcrypt('x'), 'is_active' => true,
        ]);
    }

    public function test_faculty_student_search_only_returns_their_own_department(): void
    {
        $faculty = $this->faculty();
        $mine = $this->student('2800001', 'College of Information Sciences', 'BS Information Technology');
        $otherProgram = $this->student('2800002', 'College of Information Sciences', 'BS Computer Science');
        $otherCollege = $this->student('2800003', 'College of Business', 'BS Information Technology');
        Sanctum::actingAs($faculty);

        $res = $this->getJson('/api/students?search=Cruz&college=College of Business')->assertOk();
        $ids = collect($res->json('data'))->pluck('id')->all();
        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($otherProgram->id, $ids);
        $this->assertNotContains($otherCollege->id, $ids);
    }

    public function test_faculty_cannot_refer_a_student_outside_their_department(): void
    {
        Notification::fake();
        $faculty = $this->faculty();
        $other = $this->student('2800004', 'College of Information Sciences', 'BS Computer Science');
        Sanctum::actingAs($faculty);

        $this->postJson('/api/referrals', [
            'student_id' => $other->id, 'referral_type' => 'counseling', 'nature_of_concern' => 'Needs someone to talk to.',
        ])->assertStatus(403);
    }

    public function test_faculty_can_refer_a_student_in_their_own_department(): void
    {
        Notification::fake();
        $faculty = $this->faculty();
        $mine = $this->student('2800005', 'College of Information Sciences', 'BS Information Technology');
        Sanctum::actingAs($faculty);

        $this->postJson('/api/referrals', [
            'student_id' => $mine->id, 'referral_type' => 'counseling', 'nature_of_concern' => 'Needs someone to talk to.',
        ])->assertCreated();
    }

    public function test_dept_chair_still_unaffected_by_the_faculty_scope(): void
    {
        $chair = User::factory()->create([
            'role' => 'dept_chair',
            'college' => 'College of Information Sciences',
            'department' => 'BS Information Technology',
        ]);
        $mine = $this->student('2800006', 'College of Information Sciences', 'BS Information Technology');
        Sanctum::actingAs($chair);

        $res = $this->getJson('/api/students?search=Cruz')->assertOk();
        $this->assertTrue(collect($res->json('data'))->contains('id', $mine->id));
    }
}