<?php

namespace Tests\Feature;

use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

// A no-show session has nothing to apply interventions to, so Interventions
// Applied is only required when "Student Showed Up" is checked - and never
// saved for a session that was marked as a no-show, even if something
// other than the "Add Session Notes" form (which already blanks it
// client-side) sends one anyway.
class SessionNoteAttendanceTest extends TestCase
{
    use RefreshDatabase;

    private function referral(): Referral
    {
        $student = Student::factory()->create();
        $case = CaseFile::factory()->create(['student_id' => $student->id]);
        return Referral::factory()->create(['student_id' => $student->id, 'case_id' => $case->id]);
    }

    public function test_interventions_required_when_student_attended(): void
    {
        $gcu = User::factory()->create(['role' => 'gcu_staff', 'is_active' => true]);
        Sanctum::actingAs($gcu);
        $referral = $this->referral();

        $this->postJson("/api/referrals/{$referral->id}/session-notes", [
            'session_date' => today()->toDateString(), 'session_type' => 'follow_up',
            'observations' => 'Met with student.', 'student_showed_up' => true,
        ])->assertStatus(422);
    }

    public function test_interventions_not_required_and_discarded_when_student_did_not_attend(): void
    {
        $gcu = User::factory()->create(['role' => 'gcu_staff', 'is_active' => true]);
        Sanctum::actingAs($gcu);
        $referral = $this->referral();

        $res = $this->postJson("/api/referrals/{$referral->id}/session-notes", [
            'session_date' => today()->toDateString(), 'session_type' => 'follow_up',
            'observations' => 'Student did not attend.', 'student_showed_up' => false,
            // Sent anyway, as if from something other than the guarded form.
            'interventions' => 'Should not be saved.',
        ])->assertCreated();

        $this->assertNull($res->json('interventions'));
    }
}