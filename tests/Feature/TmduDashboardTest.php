<?php

namespace Tests\Feature;

use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\Student;
use App\Models\TestingRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

// B554: the TMDU dashboard's "Pending Testing" (and In Progress / Completed)
// counts must match what the Testing Records page itself lists for that
// staff member - not a flat, system-wide count - since TestingRecordController
// ::index() already scopes a TMDU staff/head member to records assigned to
// them plus unclaimed records whose referral has been acknowledged.
class TmduDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function tmdu(string $role = 'tmdu_staff'): User
    {
        $u = User::factory()->create(['role' => $role]);
        Sanctum::actingAs($u);
        return $u;
    }

    private function pendingRecord(?int $assignedTo, bool $acknowledged, string $status = 'pending'): TestingRecord
    {
        $student = Student::factory()->create();
        $case = CaseFile::factory()->create(['student_id' => $student->id]);
        $referral = Referral::factory()->create([
            'student_id' => $student->id,
            'case_id' => $case->id,
            'acknowledged_at' => $acknowledged ? now() : null,
        ]);
        return TestingRecord::create([
            'case_id' => $case->id,
            'student_id' => $student->id,
            'referred_by_user_id' => User::factory()->create()->id,
            'referral_id' => $referral->id,
            'assigned_tester_user_id' => $assignedTo,
            'status' => $status,
        ]);
    }

    public function test_dashboard_pending_testing_count_matches_testing_records_list(): void
    {
        $tester = $this->tmdu();

        // Visible to this tester: assigned to them, and an unclaimed-but-acknowledged one.
        $this->pendingRecord($tester->id, false);
        $this->pendingRecord(null, true);
        // Not visible: unclaimed and not yet acknowledged, and assigned to someone else.
        $this->pendingRecord(null, false);
        $this->pendingRecord(User::factory()->create(['role' => 'tmdu_staff'])->id, false);

        $dash = $this->getJson('/api/dashboard')->assertOk()->json();
        $this->assertSame(2, $dash['stats']['pending_testing']);

        $list = $this->getJson('/api/testing-records?status=pending')->assertOk()->json();
        $this->assertSame(2, $list['total']);
    }

    // B554 follow-up: "In Progress" and "Completed" used to count a status
    // ('in_progress' / 'completed') that TestingRecord never actually has,
    // so both cards always read 0 regardless of real data. They now use the
    // same status groups as the Testing Records page's own stat tiles
    // (testing/Index.vue): In Progress = scheduled/test_administered/
    // awaiting_results, Completed = test_results_issued.
    public function test_dashboard_in_progress_and_completed_match_their_real_statuses(): void
    {
        $tester = $this->tmdu();

        $this->pendingRecord($tester->id, false, 'scheduled');
        $this->pendingRecord($tester->id, false, 'test_administered');
        $this->pendingRecord($tester->id, false, 'awaiting_results');
        $this->pendingRecord($tester->id, false, 'test_results_issued');
        $this->pendingRecord($tester->id, false, 'test_results_issued');
        // Not visible to this tester - must not be counted in either card.
        $this->pendingRecord(User::factory()->create(['role' => 'tmdu_staff'])->id, false, 'scheduled');

        $dash = $this->getJson('/api/dashboard')->assertOk()->json();
        $this->assertSame(3, $dash['stats']['in_progress']);
        $this->assertSame(2, $dash['stats']['completed']);
    }
}