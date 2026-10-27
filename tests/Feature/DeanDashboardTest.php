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

// B567 (superseded 10/27/2026): the Dean dashboard's "Total" (and Awaiting /
// Received / Resolved) counts originally had to match the Referral Queue's
// college-wide list. Decision (10/27/2026): the card links to Sent
// Referrals, which now shows only what the Dean personally submitted (same
// as Faculty/Dept Chair/SDU Head) - so the card counts exactly what that
// list shows: every referral the Dean sent, archived or TMDU-escalated
// included, minus Complaint-originated ones (which Sent Referrals excludes
// for everyone).
class DeanDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function dean(string $college): User
    {
        $u = User::factory()->create(['role' => 'dean', 'college' => $college]);
        Sanctum::actingAs($u);
        return $u;
    }

    private function referralFor(User $dean, string $college, array $attrs = []): Referral
    {
        $student = Student::factory()->create(['college' => $college]);
        return Referral::factory()->create(array_merge(
            ['student_id' => $student->id, 'referred_by_user_id' => $dean->id],
            $attrs
        ));
    }

    public function test_dashboard_total_matches_sent_referrals_list(): void
    {
        $college = 'College of Information Sciences (CIS)';
        $dean = $this->dean($college);

        // Visible: ordinary referral this Dean sent.
        $this->referralFor($dean, $college);
        // Visible: archived referrals still show up in Sent Referrals.
        $this->referralFor($dean, $college, ['status' => 'acknowledged', 'is_archived' => true]);

        // Visible: already escalated to TMDU (has a linked TestingRecord) -
        // Sent Referrals doesn't filter these out either.
        $escalated = $this->referralFor($dean, $college);
        $student = Student::factory()->create(['college' => $college]);
        $case = CaseFile::factory()->create(['student_id' => $student->id]);
        TestingRecord::create([
            'case_id' => $case->id,
            'student_id' => $student->id,
            'referred_by_user_id' => User::factory()->create()->id,
            'referral_id' => $escalated->id,
            'status' => 'pending',
        ]);

        // Not visible: originated from a Complaint.
        $this->referralFor($dean, $college, ['complaint_id' => 1]);

        // Not visible: sent by someone else in the same college.
        $other = User::factory()->create(['role' => 'faculty', 'college' => $college]);
        $otherStudent = Student::factory()->create(['college' => $college]);
        Referral::factory()->create(['student_id' => $otherStudent->id, 'referred_by_user_id' => $other->id]);

        $dash = $this->getJson('/api/dashboard')->assertOk()->json();
        $this->assertSame(3, $dash['stats']['my_referrals']);

        $sent = $this->getJson('/api/my/referrals')->assertOk()->json();
        $this->assertSame(3, $sent['total']);
    }
}