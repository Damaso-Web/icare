<?php

namespace Tests\Feature;

use App\Models\CaseFile;
use App\Models\Student;
use App\Models\TestingRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

// B559: reassigning a testing record away from a tester must not make it
// disappear for them - they keep read-only visibility (it still shows up in
// their Testing Records list, and GET show() still works), but can no
// longer act on it.
class TestingRecordReassignmentTest extends TestCase
{
    use RefreshDatabase;

    private function tmdu(): User
    {
        return User::factory()->create(['role' => 'tmdu_staff']);
    }

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

    public function test_previous_tester_keeps_visibility_but_loses_write_access(): void
    {
        $original = $this->tmdu();
        $newTester = $this->tmdu();
        $record = $this->record();

        Sanctum::actingAs($original);
        $this->postJson("/api/testing-records/{$record->id}/assign", ['tester_user_id' => $original->id])->assertOk();

        Sanctum::actingAs($newTester);
        $this->postJson("/api/testing-records/{$record->id}/assign", ['tester_user_id' => $newTester->id])->assertOk();
        $record->refresh();
        $this->assertSame($original->id, $record->previous_tester_user_id);
        $this->assertSame($newTester->id, $record->assigned_tester_user_id);

        // The original tester still sees it in their list...
        Sanctum::actingAs($original);
        $list = $this->getJson('/api/testing-records')->assertOk()->json('data');
        $this->assertTrue(collect($list)->contains('id', $record->id));

        // ...and can still open it directly...
        $this->getJson("/api/testing-records/{$record->id}")->assertOk();

        // ...but can no longer act on it.
        $this->postJson("/api/testing-records/{$record->id}/acknowledge")->assertStatus(403);

        // The new tester acts on it normally.
        Sanctum::actingAs($newTester);
        $this->postJson("/api/testing-records/{$record->id}/acknowledge")->assertOk();
    }

    public function test_tester_with_no_history_on_the_record_still_blocked_as_usual(): void
    {
        $uninvolved = $this->tmdu();
        $record = $this->record();

        Sanctum::actingAs($uninvolved);
        // No tester assigned at all yet - the ordinary "assign someone first" rule still applies.
        $this->postJson("/api/testing-records/{$record->id}/acknowledge")->assertStatus(422);
    }
}