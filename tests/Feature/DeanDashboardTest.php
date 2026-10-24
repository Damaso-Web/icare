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

// B567: the Dean dashboard's "Total" (and Awaiting / Received / Resolved)
// counts must match what the Referral Queue itself lists for that Dean's
// college - not a flat count of every referral from students in that
// college - since ReferralController::index() already excludes archived
// referrals, Complaint-originated ones, and anything already escalated to
// TMDU.
class DeanDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function dean(string $college): User
    {
        $u = User::factory()->create(['role' => 'dean', 'college' => $college]);
        Sanctum::actingAs($u);
        return $u;
    }

    private function referralFor(string $college, array $attrs = []): Referral
    {
        $student = Student::factory()->create(['college' => $college]);
        return Referral::factory()->create(array_merge(['student_id' => $student->id], $attrs));
    }

    public function test_dashboard_total_matches_referral_queue_list(): void
    {
        $college = 'College of Information Sciences (CIS)';
        $dean = $this->dean($college);

        // Visible: ordinary, unarchived, non-complaint, non-TMDU-escalated referrals for this college.
        $this->referralFor($college);
        $this->referralFor($college, ['status' => 'acknowledged']);

        // Not visible: archived.
        $this->referralFor($college, ['is_archived' => true]);

        // Not visible: originated from a Complaint.
        $this->referralFor($college, ['complaint_id' => 1]);

        // Not visible: already escalated to TMDU (has a linked TestingRecord).
        $escalated = $this->referralFor($college);
        $student = Student::factory()->create(['college' => $college]);
        $case = CaseFile::factory()->create(['student_id' => $student->id]);
        TestingRecord::create([
            'case_id' => $case->id,
            'student_id' => $student->id,
            'referred_by_user_id' => User::factory()->create()->id,
            'referral_id' => $escalated->id,
            'status' => 'pending',
        ]);

        // Not visible: a different college entirely.
        $this->referralFor('College of Engineering (CE)');

        $dash = $this->getJson('/api/dashboard')->assertOk()->json();
        $this->assertSame(2, $dash['stats']['my_referrals']);

        $list = $this->getJson('/api/referrals')->assertOk()->json();
        $this->assertSame(2, $list['total']);
    }
}