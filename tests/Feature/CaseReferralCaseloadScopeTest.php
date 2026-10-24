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

// Bug (10/24/2026, B624): "TMDU Testing Results" (formerly "Case Referral
// (TMDU)") must scope a gcu_staff counselor to their own caseload, same as
// Student Information Files (B623) - only the GCU Head (admin) keeps
// unit-wide visibility.
class CaseReferralCaseloadScopeTest extends TestCase
{
    use RefreshDatabase;

    private function slip(?User $counselor): Referral
    {
        $student = Student::factory()->create();
        $case = CaseFile::factory()->create(['student_id' => $student->id, 'primary_counselor_id' => $counselor?->id]);
        $referral = Referral::factory()->create(['student_id' => $student->id, 'case_id' => $case->id]);
        TestingRecord::create([
            'case_id' => $case->id,
            'referral_id' => $referral->id,
            'student_id' => $student->id,
            'referred_by_user_id' => $referral->referred_by_user_id ?? User::factory()->create(['role' => 'gcu_staff'])->id,
            'status' => 'pending',
        ]);
        return $referral;
    }

    public function test_gcu_staff_only_sees_slips_for_their_own_caseload(): void
    {
        $mine = $this->slip($counselor = User::factory()->create(['role' => 'gcu_staff']));
        $other = $this->slip(User::factory()->create(['role' => 'gcu_staff']));

        Sanctum::actingAs($counselor);
        $list = $this->getJson('/api/case-referrals')->assertOk()->json('data');
        $ids = collect($list)->pluck('id')->all();
        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($other->id, $ids);

        $this->getJson("/api/case-referrals/{$mine->id}")->assertOk();
        $this->getJson("/api/case-referrals/{$other->id}")->assertStatus(403);
    }

    public function test_admin_sees_every_slip(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $a = $this->slip(User::factory()->create(['role' => 'gcu_staff']));
        $b = $this->slip(User::factory()->create(['role' => 'gcu_staff']));

        Sanctum::actingAs($admin);
        $list = $this->getJson('/api/case-referrals')->assertOk()->json('data');
        $ids = collect($list)->pluck('id')->all();
        $this->assertContains($a->id, $ids);
        $this->assertContains($b->id, $ids);
    }
}