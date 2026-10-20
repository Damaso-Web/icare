<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SentListFiltersTest extends TestCase
{
    use RefreshDatabase;

    private function student(string $id, string $first): Student
    {
        return Student::create([
            'student_id' => $id, 'first_name' => $first, 'last_name' => 'Cruz', 'year_level' => '1st Year',
            'college' => 'CIT', 'program' => 'BSIT', 'password' => Hash::make('x'), 'is_active' => true,
        ]);
    }

    private function complaint(Student $s, ?User $by, string $code, string $type, string $when): Complaint
    {
        $c = Complaint::create([
            'complaint_code' => $code, 'complainant_name' => 'X', 'complainee_student_id' => $s->id,
            'violation_type' => $type, 'description' => 'd', 'incident_date' => now()->toDateString(),
            'filed_by_user_id' => $by?->id, 'status' => 'pending', 'certification_agreed' => true,
        ]);
        $c->forceFill(['created_at' => $when])->save();
        return $c;
    }

    public function test_complaints_list_filters_sorts_and_offers_options(): void
    {
        $admin = User::factory()->create(['role' => 'sdu_head', 'is_active' => true]);
        $other = User::factory()->create(['role' => 'faculty', 'is_active' => true]);
        $s = $this->student('2300001', 'Ana');
        $this->complaint($s, $admin, 'C-1', 'Cheating', '2026-01-01 00:00:00');
        $this->complaint($s, $other, 'C-2', 'Bullying', '2026-02-01 00:00:00');
        Sanctum::actingAs($admin);

        $r = $this->getJson('/api/complaints?sort=oldest')->assertOk();
        $this->assertSame('Cheating', $r->json('data.0.violation_type'));
        $this->assertSame('Bullying', $this->getJson('/api/complaints')->json('data.0.violation_type'));
        $this->assertEqualsCanonicalizing(['Bullying', 'Cheating'], $r->json('filter_options.violation_types'));

        $this->getJson('/api/complaints?violation_type=Bullying')->assertJsonCount(1, 'data');
        $this->getJson('/api/complaints?filed_by=u:' . $other->id)->assertJsonCount(1, 'data');
        $this->getJson('/api/complaints?search=Ana')->assertJsonCount(2, 'data');

        $this->getJson('/api/my/complaints?violation_type=Cheating')->assertJsonCount(1, 'data');
        $this->getJson('/api/my/complaints?violation_type=Bullying')->assertJsonCount(0, 'data');
    }
    public function test_sent_filters_list_every_option_even_when_unused(): void
    {
        $sdu = User::factory()->create(['role' => 'sdu_head', 'is_active' => true]);
        \App\Models\ReferralFormOption::create(['category' => 'referral_type', 'value' => 'counseling', 'label' => 'Counseling', 'sort_order' => 1]);
        \App\Models\ReferralFormOption::create(['category' => 'referral_type', 'value' => 'shifting', 'label' => 'Shifting', 'sort_order' => 2]);
        \App\Models\ReferralFormOption::create(['category' => 'act_of_misconduct', 'value' => 'cheating', 'label' => 'Cheating', 'sort_order' => 1]);
        Sanctum::actingAs($sdu);

        $ref = $this->getJson('/api/my/referrals')->assertOk();
        $this->assertContains('counseling', $ref->json('filter_options.types'));
        $this->assertContains('shifting', $ref->json('filter_options.types'));
        $this->assertContains('scheduled', $ref->json('filter_options.statuses'));
        $this->assertContains('in_progress', $ref->json('filter_options.statuses'));

        $c = $this->getJson('/api/my/complaints')->assertOk();
        $this->assertContains('Cheating', $c->json('filter_options.violation_types'));
        $this->assertEqualsCanonicalizing(['pending', 'under_review', 'resolved'], $c->json('filter_options.statuses'));
    }
}