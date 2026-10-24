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

// B564: the Faculty dashboard's "My Referrals" (and Pending / Acknowledged /
// Completed) counts must match what the Referral Queue itself lists for
// that faculty member - not a flat count of every referral they ever sent -
// since ReferralController::index() already excludes archived referrals,
// Complaint-originated ones, and anything already escalated to TMDU.
class FacultyDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function faculty(): User
    {
        $u = User::factory()->create(['role' => 'faculty']);
        Sanctum::actingAs($u);
        return $u;
    }

    private function referral(User $faculty, array $attrs = []): Referral
    {
        return Referral::factory()->create(array_merge([
            'referred_by_user_id' => $faculty->id,
        ], $attrs));
    }

    public function test_dashboard_referral_counts_match_referral_queue_list(): void
    {
        $faculty = $this->faculty();

        // Visible: an ordinary, unarchived, non-complaint, non-TMDU-escalated referral.
        $this->referral($faculty);
        $this->referral($faculty, ['status' => 'acknowledged']);

        // Not visible: archived.
        $this->referral($faculty, ['is_archived' => true]);

        // Not visible: originated from a Complaint.
        $this->referral($faculty, ['complaint_id' => 1]);

        // Not visible: already escalated to TMDU (has a linked TestingRecord).
        $escalated = $this->referral($faculty);
        $student = Student::factory()->create();
        $case = CaseFile::factory()->create(['student_id' => $student->id]);
        TestingRecord::create([
            'case_id' => $case->id,
            'student_id' => $student->id,
            'referred_by_user_id' => $faculty->id,
            'referral_id' => $escalated->id,
            'status' => 'pending',
        ]);

        // Not visible: belongs to a different faculty member.
        $this->referral(User::factory()->create(['role' => 'faculty']));

        $dash = $this->getJson('/api/dashboard')->assertOk()->json();
        $this->assertSame(2, $dash['stats']['my_referrals']);

        $list = $this->getJson('/api/referrals')->assertOk()->json();
        $this->assertSame(2, $list['total']);
    }
}