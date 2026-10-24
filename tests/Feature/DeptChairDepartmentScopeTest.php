<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

// Bug (10/14/2026): a Dept Chair's student lookup (and the referral/
// complaint it feeds) is scoped to their own department - mirrors
// DeanCollegeScopeTest (B590), one level further: college AND program
// (shown to the Chair as "Department") must both match.
class DeptChairDepartmentScopeTest extends TestCase
{
    use RefreshDatabase;

    private function chair(): User
    {
        return User::factory()->create([
            'role' => 'dept_chair',
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

    public function test_dept_chair_student_search_only_returns_their_own_department(): void
    {
        $chair = $this->chair();
        $mine = $this->student('2700001', 'College of Information Sciences', 'BS Information Technology');
        $otherProgram = $this->student('2700002', 'College of Information Sciences', 'BS Computer Science');
        $otherCollege = $this->student('2700003', 'College of Business', 'BS Information Technology');
        Sanctum::actingAs($chair);

        // Even trying to force another department/college via query params is ignored.
        $res = $this->getJson('/api/students?search=Cruz&college=College of Business')->assertOk();
        $ids = collect($res->json('data'))->pluck('id')->all();
        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($otherProgram->id, $ids);
        $this->assertNotContains($otherCollege->id, $ids);
    }

    public function test_dept_chair_cannot_refer_a_student_outside_their_department(): void
    {
        Notification::fake();
        $chair = $this->chair();
        $other = $this->student('2700004', 'College of Information Sciences', 'BS Computer Science');
        Sanctum::actingAs($chair);

        $this->postJson('/api/referrals', [
            'student_id' => $other->id, 'referral_type' => 'counseling', 'nature_of_concern' => 'Needs someone to talk to.',
        ])->assertStatus(403);
    }

    public function test_dept_chair_can_refer_a_student_in_their_own_department(): void
    {
        Notification::fake();
        $chair = $this->chair();
        $mine = $this->student('2700005', 'College of Information Sciences', 'BS Information Technology');
        Sanctum::actingAs($chair);

        $this->postJson('/api/referrals', [
            'student_id' => $mine->id, 'referral_type' => 'counseling', 'nature_of_concern' => 'Needs someone to talk to.',
        ])->assertCreated();
    }

    public function test_dept_chair_cannot_file_a_complaint_against_a_student_outside_their_department(): void
    {
        Notification::fake();
        \Illuminate\Support\Facades\Storage::fake('public');
        $chair = $this->chair();
        $other = $this->student('2700006', 'College of Information Sciences', 'BS Computer Science');
        Sanctum::actingAs($chair);

        $file = fn ($n) => \Illuminate\Http\UploadedFile::fake()->create($n, 10);
        $this->postJson('/api/complaints', [
            'complainant_name' => $chair->name,
            'complainant_address' => 'Batangas City', 'complainee_student_id' => $other->id,
            'violation_type' => 'Bullying', 'incident_date' => now()->subDay()->toDateString(),
            'description' => 'A believable account of what happened.',
            'certification_agreed' => '1',
            'evidence' => [$file('e.pdf')], 'affidavit' => [$file('a.pdf')],
        ])->assertStatus(403);
    }
}