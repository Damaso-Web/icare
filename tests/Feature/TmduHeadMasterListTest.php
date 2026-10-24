<?php

namespace Tests\Feature;

use App\Models\CaseFile;
use App\Models\Student;
use App\Models\TestingRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

// Bug (10/24/2026): the TMDU Head's Testing Records module is the unit's
// master historical database - it must keep showing every record no
// matter who it's assigned/reassigned to, unlike a regular tmdu_staff
// tester's own narrower queue (TestingRecordReassignmentTest, B559).
class TmduHeadMasterListTest extends TestCase
{
    use RefreshDatabase;

    private function record(): TestingRecord
    {
        $student = Student::factory()->create();
        $case = CaseFile::factory()->create(['student_id' => $student->id]);
        return TestingRecord::create([
            'case_id' => $case->id,
            'student_id' => $student->id,
            'referred_by_user_id' => User::factory()->create()->id,
            'status' => 'pending',
        ]);
    }

    public function test_head_still_sees_record_after_it_is_assigned_to_a_staff_member(): void
    {
        $head = User::factory()->create(['role' => 'tmdu_head']);
        $staff = User::factory()->create(['role' => 'tmdu_staff']);
        $record = $this->record();

        Sanctum::actingAs($staff);
        $this->postJson("/api/testing-records/{$record->id}/assign", ['tester_user_id' => $staff->id])->assertOk();

        Sanctum::actingAs($head);
        $list = $this->getJson('/api/testing-records')->assertOk()->json('data');
        $this->assertTrue(collect($list)->contains('id', $record->id));
    }

    public function test_head_still_sees_record_after_reassignment_to_a_different_staff_member(): void
    {
        $head = User::factory()->create(['role' => 'tmdu_head']);
        $staffA = User::factory()->create(['role' => 'tmdu_staff']);
        $staffB = User::factory()->create(['role' => 'tmdu_staff']);
        $record = $this->record();

        Sanctum::actingAs($staffA);
        $this->postJson("/api/testing-records/{$record->id}/assign", ['tester_user_id' => $staffA->id])->assertOk();
        $this->postJson("/api/testing-records/{$record->id}/assign", ['tester_user_id' => $staffB->id])->assertOk();

        Sanctum::actingAs($head);
        $list = $this->getJson('/api/testing-records')->assertOk()->json('data');
        $this->assertTrue(collect($list)->contains('id', $record->id));

        // And the metadata (tester, status) is visible and up to date.
        $entry = collect($list)->firstWhere('id', $record->id);
        $this->assertSame($staffB->id, $entry['assigned_tester_user_id']);
    }

    public function test_staffs_own_queue_still_narrows_as_before(): void
    {
        $staffA = User::factory()->create(['role' => 'tmdu_staff']);
        $staffB = User::factory()->create(['role' => 'tmdu_staff']);
        $record = $this->record();

        Sanctum::actingAs($staffA);
        $this->postJson("/api/testing-records/{$record->id}/assign", ['tester_user_id' => $staffB->id])->assertOk();

        Sanctum::actingAs($staffB);
        $this->postJson("/api/testing-records/{$record->id}/assign", ['tester_user_id' => $staffB->id])->assertOk();

        // A third, uninvolved staff member never had this record and
        // shouldn't see it in their own queue.
        $uninvolved = User::factory()->create(['role' => 'tmdu_staff']);
        Sanctum::actingAs($uninvolved);
        $list = $this->getJson('/api/testing-records')->assertOk()->json('data');
        $this->assertFalse(collect($list)->contains('id', $record->id));
    }
}