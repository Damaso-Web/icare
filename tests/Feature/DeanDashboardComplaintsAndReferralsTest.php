<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\Referral;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

// B633: the Dean dashboard had no "My Submitted Complaints" section at all.
// B634 (superseded 10/27/2026): the Dean dashboard's referral cards counted
// college-wide while Sent Referrals showed personal-only, so a card's count
// didn't match its own list. Decision (10/27/2026): Sent Referrals - and the
// cards that link to it - now both show only what the Dean personally
// submitted, same as Faculty/Dept Chair/SDU Head. The college-wide view is
// the Referral Queue, a separate page.
class DeanDashboardComplaintsAndReferralsTest extends TestCase
{
    use RefreshDatabase;

    private function student(string $id, string $college = 'CIT'): Student
    {
        return Student::create([
            'student_id' => $id, 'first_name' => 'Juan', 'last_name' => 'Cruz', 'year_level' => '1st Year',
            'college' => $college, 'program' => 'BSIT', 'password' => Hash::make('x'), 'is_active' => true,
        ]);
    }

    public function test_dean_dashboard_includes_their_own_submitted_complaints(): void
    {
        $dean = User::factory()->create(['role' => 'dean', 'college' => 'CIT']);
        $other = User::factory()->create(['role' => 'dean', 'college' => 'CIT']);
        $complainee = $this->student('2700001');

        Complaint::create([
            'filed_by_user_id' => $dean->id, 'complainee_student_id' => $complainee->id,
            'complainant_name' => $dean->name, 'complainant_address' => 'Somewhere',
            'complainee_college' => 'CIT', 'complainee_department' => 'BSIT',
            'violation_type' => 'Other', 'incident_date' => today(), 'description' => 'Minor issue.',
            'status' => 'pending',
        ]);
        Complaint::create([
            'filed_by_user_id' => $other->id, 'complainee_student_id' => $complainee->id,
            'complainant_name' => $other->name, 'complainant_address' => 'Somewhere else',
            'complainee_college' => 'CIT', 'complainee_department' => 'BSIT',
            'violation_type' => 'Other', 'incident_date' => today(), 'description' => 'Not this Dean\'s.',
            'status' => 'pending',
        ]);

        Sanctum::actingAs($dean);
        $res = $this->getJson('/api/dashboard')->assertOk();
        $res->assertJsonCount(1, 'recent_complaints');
        $this->assertSame($dean->id, $res->json('recent_complaints.0.filed_by_user_id'));
    }

    private function submitReferral(User $actor, Student $student, string $text): void
    {
        Sanctum::actingAs($actor);
        $this->postJson('/api/referrals', [
            'student_id' => $student->id, 'referral_type' => 'counseling', 'nature_of_concern' => $text,
        ])->assertCreated();
    }

    public function test_dean_sent_referrals_matches_their_dashboard_card_count(): void
    {
        Notification::fake();
        $dean    = User::factory()->create(['role' => 'dean', 'college' => 'CIT']);
        $faculty = User::factory()->create(['role' => 'faculty', 'college' => 'CIT', 'department' => 'BSIT']);
        $student = $this->student('2700002');

        // One referral from the Dean, one from a different faculty member in
        // the same college - the Dean's dashboard card and Sent Referrals
        // both count only the Dean's own (1), not the faculty member's.
        $this->submitReferral($dean, $student, 'Needs guidance.');
        $this->submitReferral($faculty, $student, 'Needs guidance too.');

        Sanctum::actingAs($dean);
        $dashboard = $this->getJson('/api/dashboard')->assertOk();
        $cardCount = $dashboard->json('stats.my_referrals');

        $sent = $this->getJson('/api/my/referrals')->assertOk();
        $this->assertSame(1, $cardCount);
        $sent->assertJsonCount(1, 'data');
    }

    public function test_other_roles_sent_referrals_still_personal_only(): void
    {
        Notification::fake();
        $facultyA = User::factory()->create(['role' => 'faculty', 'college' => 'CIT', 'department' => 'BSIT']);
        $facultyB = User::factory()->create(['role' => 'faculty', 'college' => 'CIT', 'department' => 'BSIT']);
        $student  = $this->student('2700003');

        $this->submitReferral($facultyA, $student, 'Needs guidance.');
        $this->submitReferral($facultyB, $student, 'Needs guidance too.');

        Sanctum::actingAs($facultyA);
        $this->getJson('/api/my/referrals')->assertOk()->assertJsonCount(1, 'data');
    }
}