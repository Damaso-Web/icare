<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\StudentStatusEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

// Follow-up to panel item #9 (11/10/2026): "Select All" reaches students on
// every page (matching-ids), and the admin can reactivate several inactive
// students at once the same way they can deactivate them (bulk-activate).
class BulkActivateAndSelectAllTest extends TestCase
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

    // ---- bulk-activate ------------------------------------------------

    public function test_admin_can_bulk_activate_several_inactive_students(): void
    {
        $admin = $this->admin();
        Sanctum::actingAs($admin);
        $students = Student::factory()->count(3)->create(['is_active' => false, 'deactivation_reason' => 'graduated']);

        $res = $this->postJson('/api/students/bulk-activate', [
            'student_ids' => $students->pluck('id')->all(),
            'reason'      => 're_enrolled',
        ])->assertOk();

        $this->assertCount(3, $res->json('activated'));
        $this->assertCount(0, $res->json('skipped'));
        foreach ($students as $s) {
            $s->refresh();
            $this->assertTrue($s->is_active);
            // Same rule as the single Reactivate: a new password at next login.
            $this->assertTrue($s->must_change_password);
            $this->assertDatabaseHas('student_status_events', [
                'student_id' => $s->id,
                'action'     => 'reactivated',
                'reason_key' => 're_enrolled',
                'actor_id'   => $admin->id,
            ]);
        }
    }

    public function test_bulk_activate_skips_a_student_who_is_already_active(): void
    {
        Sanctum::actingAs($this->admin());
        $inactive = Student::factory()->create(['is_active' => false]);
        $active   = Student::factory()->create(['is_active' => true]);

        $res = $this->postJson('/api/students/bulk-activate', [
            'student_ids' => [$inactive->id, $active->id],
            'reason'      => 'error',
        ])->assertOk();

        $this->assertCount(1, $res->json('activated'));
        $this->assertCount(1, $res->json('skipped'));
        $this->assertSame($active->id, $res->json('skipped.0.id'));
        $this->assertSame(1, StudentStatusEvent::where('action', 'reactivated')->count());
    }

    public function test_bulk_activate_requires_a_reason_and_students(): void
    {
        Sanctum::actingAs($this->admin());
        $student = Student::factory()->create(['is_active' => false]);

        $this->postJson('/api/students/bulk-activate', ['student_ids' => [$student->id]])
            ->assertStatus(422)->assertJsonValidationErrors('reason');
        $this->postJson('/api/students/bulk-activate', ['reason' => 'error'])
            ->assertStatus(422)->assertJsonValidationErrors('student_ids');
        // "Other" needs an explanation.
        $this->postJson('/api/students/bulk-activate', ['student_ids' => [$student->id], 'reason' => 'other'])
            ->assertStatus(422)->assertJsonValidationErrors('note');

        $this->assertFalse($student->refresh()->is_active);
    }

    public function test_only_an_admin_can_bulk_activate(): void
    {
        Sanctum::actingAs($this->dean());
        $student = Student::factory()->create(['is_active' => false]);

        $this->postJson('/api/students/bulk-activate', ['student_ids' => [$student->id], 'reason' => 'error'])
            ->assertForbidden();

        $this->assertFalse($student->refresh()->is_active);
    }

    // ---- matching-ids (Select All across every page) -------------------

    public function test_matching_ids_returns_students_from_every_page(): void
    {
        Sanctum::actingAs($this->admin());
        $active = Student::factory()->count(25)->create(['is_active' => true]);
        Student::factory()->count(4)->create(['is_active' => false]);

        $res = $this->getJson('/api/students/matching-ids?is_active=true')->assertOk();

        // The list pages 10 at a time; Select All still gets all 25.
        $this->assertSame(25, $res->json('total'));
        $this->assertEqualsCanonicalizing($active->pluck('id')->all(), $res->json('ids'));
    }

    public function test_matching_ids_follow_the_search_and_college_filters(): void
    {
        Sanctum::actingAs($this->admin());
        $match = Student::factory()->count(2)->create(['is_active' => true, 'college' => 'College of Information Sciences', 'last_name' => 'Zamora']);
        Student::factory()->create(['is_active' => true, 'college' => 'College of Information Sciences', 'last_name' => 'Other']);
        Student::factory()->create(['is_active' => true, 'college' => 'College of Nursing', 'last_name' => 'Zamora']);

        $res = $this->getJson('/api/students/matching-ids?is_active=true&search=Zamora&college=' . urlencode('College of Information Sciences'))
            ->assertOk();

        $this->assertEqualsCanonicalizing($match->pluck('id')->all(), $res->json('ids'));
    }

    public function test_matching_ids_is_admin_only(): void
    {
        Sanctum::actingAs($this->dean());

        $this->getJson('/api/students/matching-ids')->assertForbidden();
    }
}