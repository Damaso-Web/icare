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

    private function pendingRecord(?int $assignedTo, bool $acknowledged): TestingRecord
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
            'status' => 'pending',
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
}