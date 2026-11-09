<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

// Panel fix (11/8/2026, item #9): Student Management and Faculty Management
// can both deactivate several selected accounts at once with one shared
// reason, instead of repeating the single-account Deactivate flow one at a
// time. StudentController::bulkDeactivate() and
// FacultyController::bulkDeactivate().
class BulkDeactivateTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    private function dean(): User
    {
        return User::factory()->create(['role' => 'dean', 'college' => 'College of Information Sciences', 'is_active' => true]);
    }

    // ---- Students ----------------------------------------------------

    public function test_admin_can_bulk_deactivate_several_active_students(): void
    {
        Sanctum::actingAs($this->admin());
        $students = Student::factory()->count(3)->create(['is_active' => true]);

        $res = $this->postJson('/api/students/bulk-deactivate', [
            'student_ids'         => $students->pluck('id')->all(),
            'deactivation_reason' => 'graduated',
        ])->assertOk();

        $this->assertCount(3, $res->json('deactivated'));
        $this->assertCount(0, $res->json('skipped'));
        foreach ($students as $s) {
            $this->assertFalse($s->refresh()->is_active);
            $this->assertEquals('graduated', $s->deactivation_reason);
        }
    }

    public function test_bulk_deactivate_skips_a_student_already_deactivated(): void
    {
        Sanctum::actingAs($this->admin());
        $active = Student::factory()->create(['is_active' => true]);
        $already = Student::factory()->create(['is_active' => false]);

        $res = $this->postJson('/api/students/bulk-deactivate', [
            'student_ids'         => [$active->id, $already->id],
            'deactivation_reason' => 'graduated',
        ])->assertOk();

        $this->assertCount(1, $res->json('deactivated'));
        $this->assertCount(1, $res->json('skipped'));
        $this->assertFalse($active->refresh()->is_active);
    }

    public function test_bulk_deactivate_requires_a_reason_and_at_least_one_student(): void
    {
        Sanctum::actingAs($this->admin());
        $student = Student::factory()->create(['is_active' => true]);

        $this->postJson('/api/students/bulk-deactivate', ['student_ids' => [$student->id]])
            ->assertStatus(422)->assertJsonValidationErrors('deactivation_reason');

        $this->postJson('/api/students/bulk-deactivate', ['deactivation_reason' => 'graduated'])
            ->assertStatus(422)->assertJsonValidationErrors('student_ids');
    }

    public function test_bulk_deactivate_closes_the_students_open_cases(): void
    {
        Sanctum::actingAs($this->admin());
        $student = Student::factory()->create(['is_active' => true]);
        $case = \App\Models\CaseFile::factory()->create(['student_id' => $student->id, 'status' => 'open']);

        $this->postJson('/api/students/bulk-deactivate', [
            'student_ids'         => [$student->id],
            'deactivation_reason' => 'graduated',
        ])->assertOk();

        $this->assertEquals('closed', $case->refresh()->status);
    }

    // ---- Faculty -------------------------------------------------------

    public function test_dean_can_bulk_deactivate_several_faculty_in_their_college(): void
    {
        $dean = $this->dean();
        Sanctum::actingAs($dean);
        $faculty = User::factory()->count(2)->create(['role' => 'faculty', 'college' => $dean->college, 'is_active' => true]);

        $res = $this->postJson('/api/faculty/bulk-deactivate', [
            'user_ids' => $faculty->pluck('id')->all(),
            'reason'   => 'resigned',
        ])->assertOk();

        $this->assertCount(2, $res->json('deactivated'));
        foreach ($faculty as $f) {
            $this->assertFalse($f->refresh()->is_active);
        }
    }

    public function test_bulk_deactivate_skips_a_faculty_member_already_deactivated(): void
    {
        $dean = $this->dean();
        Sanctum::actingAs($dean);
        $active = User::factory()->create(['role' => 'faculty', 'college' => $dean->college, 'is_active' => true]);
        $already = User::factory()->create(['role' => 'faculty', 'college' => $dean->college, 'is_active' => false]);

        $res = $this->postJson('/api/faculty/bulk-deactivate', [
            'user_ids' => [$active->id, $already->id],
            'reason'   => 'resigned',
        ])->assertOk();

        $this->assertCount(1, $res->json('deactivated'));
        $this->assertCount(1, $res->json('skipped'));
        $this->assertFalse($active->refresh()->is_active);
    }

    // A Dept Chair's own account (role dept_chair) never appears in their
    // own scoped faculty list - scoped() narrows a Dept Chair's view to
    // role "faculty" in their department only - so there is no selected-id
    // path by which they could deactivate themselves via this endpoint.
    public function test_dept_chair_can_only_bulk_deactivate_faculty_in_their_own_department(): void
    {
        $chair = User::factory()->create(['role' => 'dept_chair', 'college' => 'College of Information Sciences', 'department' => 'BSIT', 'is_active' => true]);
        Sanctum::actingAs($chair);
        $ownDept = User::factory()->create(['role' => 'faculty', 'college' => $chair->college, 'department' => 'BSIT', 'is_active' => true]);
        $otherDept = User::factory()->create(['role' => 'faculty', 'college' => $chair->college, 'department' => 'BSCS', 'is_active' => true]);

        $res = $this->postJson('/api/faculty/bulk-deactivate', [
            'user_ids' => [$ownDept->id, $otherDept->id, $chair->id],
            'reason'   => 'resigned',
        ])->assertOk();

        $this->assertEquals([$ownDept->id], $res->json('deactivated.*.id'));
        $this->assertFalse($ownDept->refresh()->is_active);
        $this->assertTrue($otherDept->refresh()->is_active);
        $this->assertTrue($chair->refresh()->is_active);
    }

    public function test_bulk_deactivate_does_not_touch_faculty_outside_the_callers_college(): void
    {
        $dean = $this->dean();
        Sanctum::actingAs($dean);
        $inCollege = User::factory()->create(['role' => 'faculty', 'college' => $dean->college, 'is_active' => true]);
        $otherCollege = User::factory()->create(['role' => 'faculty', 'college' => 'College of Agriculture', 'is_active' => true]);

        $res = $this->postJson('/api/faculty/bulk-deactivate', [
            'user_ids' => [$inCollege->id, $otherCollege->id],
            'reason'   => 'resigned',
        ])->assertOk();

        $this->assertTrue(in_array($inCollege->id, $res->json('deactivated.*.id')));
        $this->assertFalse(in_array($otherCollege->id, $res->json('deactivated.*.id')));
        $this->assertTrue($otherCollege->refresh()->is_active);
    }
}