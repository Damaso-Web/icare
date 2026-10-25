<?php

namespace Tests\Feature;

use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\Student;
use App\Models\User;
use App\Notifications\CaseUpdateNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

// B562: reassigning a case away from a GCU counselor must not make it
// disappear for them - they keep read-only visibility, but can no longer
// act on it. B563: both the outgoing and the newly assigned counselor must
// be notified when the GCU Admin reassigns a case.
class CaseReassignmentTest extends TestCase
{
    use RefreshDatabase;

    private function gcu(): User
    {
        return User::factory()->create(['role' => 'gcu_staff']);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    // index() only lists a case once GCU has acknowledged at least one of
    // its referrals, so a case meant to show up in the SIF list needs one.
    private function caseWithCounselor(?User $counselor): CaseFile
    {
        $student = Student::factory()->create();
        $case = CaseFile::factory()->create([
            'student_id' => $student->id,
            'primary_counselor_id' => $counselor?->id,
        ]);
        Referral::factory()->create([
            'student_id' => $student->id,
            'case_id' => $case->id,
            'acknowledged_at' => now(),
        ]);
        return $case;
    }

    public function test_previous_counselor_keeps_visibility_but_loses_write_access(): void
    {
        Notification::fake();

        $original = $this->gcu();
        $newCounselor = $this->gcu();
        $admin = $this->admin();
        $case = $this->caseWithCounselor($original);

        Sanctum::actingAs($admin);
        $this->putJson("/api/cases/{$case->id}", ['primary_counselor_id' => $newCounselor->id])->assertOk();
        $case->refresh();
        $this->assertSame($original->id, $case->previous_counselor_id);
        $this->assertSame($newCounselor->id, $case->primary_counselor_id);

        // B563: both sides get the right notification, worded exactly as asked.
        Notification::assertSentTo($original, CaseUpdateNotification::class, function ($n) {
            return $n->message === 'Your case was reassigned to another counselor.';
        });
        Notification::assertSentTo($newCounselor, CaseUpdateNotification::class, function ($n) use ($case) {
            return $n->message === "The case {$case->case_number} was reassigned to you by the admin.";
        });

        // The original counselor still sees it in the Student Information Files list...
        Sanctum::actingAs($original);
        $list = $this->getJson('/api/cases')->assertOk()->json('data');
        $this->assertTrue(collect($list)->contains('id', $case->id));

        // ...and can still open it directly...
        $this->getJson("/api/cases/{$case->id}")->assertOk();

        // ...but can no longer act on it.
        $this->patchJson("/api/cases/{$case->id}/status", ['status' => 'in_progress'])->assertStatus(403);

        // The new counselor acts on it normally.
        Sanctum::actingAs($newCounselor);
        $this->patchJson("/api/cases/{$case->id}/status", ['status' => 'in_progress'])->assertOk();
    }

    // B623 reverted (10/25/2026): back to "team model" access - any
    // gcu_staff member may see and act on a case even with no history on
    // it, same as before B623 introduced strict per-counselor separation.
    public function test_counselor_with_no_history_on_the_case_can_still_see_and_act_on_it(): void
    {
        $uninvolved = $this->gcu();
        $case = $this->caseWithCounselor($this->gcu());

        Sanctum::actingAs($uninvolved);
        $this->assertNull($case->previous_counselor_id);
        $this->patchJson("/api/cases/{$case->id}/status", ['status' => 'in_progress'])->assertOk();
        $this->getJson("/api/cases/{$case->id}")->assertOk();

        $list = $this->getJson('/api/cases')->assertOk()->json('data');
        $this->assertTrue(collect($list)->contains('id', $case->id));
    }

    public function test_an_unassigned_case_can_be_acted_on_by_any_gcu_staff(): void
    {
        $staff = $this->gcu();
        $case = $this->caseWithCounselor(null);

        Sanctum::actingAs($staff);
        $this->patchJson("/api/cases/{$case->id}/status", ['status' => 'in_progress'])->assertOk();
    }

    public function test_admin_keeps_unit_wide_visibility_and_write_access(): void
    {
        $admin = $this->admin();
        $case = $this->caseWithCounselor($this->gcu());

        Sanctum::actingAs($admin);
        $this->getJson("/api/cases/{$case->id}")->assertOk();
        $list = $this->getJson('/api/cases')->assertOk()->json('data');
        $this->assertTrue(collect($list)->contains('id', $case->id));
        $this->patchJson("/api/cases/{$case->id}/status", ['status' => 'in_progress'])->assertOk();
    }

    public function test_only_admin_can_reassign_a_case(): void
    {
        $staff = $this->gcu();
        $other = $this->gcu();
        $case = $this->caseWithCounselor($staff);

        Sanctum::actingAs($staff);
        $this->putJson("/api/cases/{$case->id}", ['primary_counselor_id' => $other->id])->assertStatus(403);
    }
}