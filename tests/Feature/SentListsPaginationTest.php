<?php

namespace Tests\Feature;

use App\Models\CaseFile;
use App\Models\Complaint;
use App\Models\Referral;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

// B#, 11/4/2026: Sent Referrals and Sent Complaints used to page at 20;
// now matches Students' own page size of 10.
class SentListsPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_sent_referrals_pages_at_ten(): void
    {
        $staff = User::factory()->create(['role' => 'gcu_staff']);
        Sanctum::actingAs($staff);

        $student = Student::factory()->create();
        $case = CaseFile::factory()->create(['student_id' => $student->id]);
        Referral::factory()->count(15)->create([
            'student_id' => $student->id, 'case_id' => $case->id, 'referred_by_user_id' => $staff->id,
        ]);

        $res = $this->getJson('/api/my/referrals')->assertOk();
        $this->assertCount(10, $res->json('data'));
        $this->assertSame(10, $res->json('per_page'));
        $this->assertSame(2, $res->json('last_page'));
    }

    public function test_sent_complaints_pages_at_ten(): void
    {
        $staff = User::factory()->create(['role' => 'gcu_staff']);
        Sanctum::actingAs($staff);

        $complainee = Student::factory()->create();
        for ($i = 0; $i < 15; $i++) {
            Complaint::create([
                'filed_by_user_id' => $staff->id, 'complainee_student_id' => $complainee->id,
                'complainant_name' => $staff->name, 'complainant_address' => 'Somewhere',
                'complainee_college' => 'CIT', 'complainee_department' => 'BSIT',
                'violation_type' => 'Other', 'incident_date' => today(), 'description' => 'Issue ' . $i,
                'status' => 'pending',
            ]);
        }

        $res = $this->getJson('/api/my/complaints')->assertOk();
        $this->assertCount(10, $res->json('data'));
        $this->assertSame(10, $res->json('per_page'));
        $this->assertSame(2, $res->json('last_page'));
    }
}