<?php

namespace Tests\Feature;

use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\Student;
use App\Models\TestingRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

// A referral's feedback_* columns (feedback_notes, feedback_checklist, etc.)
// live directly on the referrals table, so every endpoint that hands a
// referral - or an appointment/testing record carrying one - back to a
// student must strip them, not just the one relation fixed earlier. This
// covers StudentAuthController's dashboard/showReferral/showAppointment/
// testingRecords/showTestingRecord.
class StudentFeedbackSlipHiddenTest extends TestCase
{
    use RefreshDatabase;

    private function student(): Student
    {
        return Student::create([
            'student_id' => '2312948', 'first_name' => 'Sunshine', 'last_name' => 'Galo', 'year_level' => '2nd Year',
            'college' => 'College of Medicine (CM)', 'program' => 'BS Medical Technology', 'section' => 'A',
            'password' => Hash::make('x'), 'is_active' => true, 'email' => 'sun@gmail.com',
        ]);
    }

    private function feedbackReferral(Student $student, CaseFile $case): Referral
    {
        $referral = Referral::factory()->create([
            'student_id' => $student->id, 'case_id' => $case->id, 'status' => 'completed',
        ]);
        $referral->update([
            'feedback_notes' => 'Confidential staff notes about the session.',
            'feedback_checklist' => json_encode(['counseling']),
            'feedback_sent_at' => now(),
            'feedback_sent_by_user_id' => User::factory()->create()->id,
        ]);

        return $referral;
    }

    private function assertNoFeedbackFields(array $referralJson): void
    {
        foreach (['feedback_notes', 'feedback_checklist', 'feedback_referred_other_text', 'feedback_others_text', 'feedback_ctrl_no', 'feedback_sent_at', 'feedback_sent_by_user_id'] as $field) {
            $this->assertArrayNotHasKey($field, $referralJson, "student response leaked {$field}");
        }
    }

    public function test_show_referral_hides_feedback_slip(): void
    {
        $student = $this->student();
        $case = CaseFile::factory()->create(['student_id' => $student->id]);
        $referral = $this->feedbackReferral($student, $case);

        Sanctum::actingAs($student, [], 'student');

        $res = $this->getJson("/api/student/referrals/{$referral->id}")->assertOk();
        $this->assertNoFeedbackFields($res->json());
    }

    public function test_dashboard_hides_feedback_slip_on_referrals_and_appointments(): void
    {
        $student = $this->student();
        $case = CaseFile::factory()->create(['student_id' => $student->id]);
        $referral = $this->feedbackReferral($student, $case);
        \App\Models\Appointment::create([
            'appointment_code' => 'APT-' . uniqid(), 'case_id' => $case->id, 'referral_id' => $referral->id,
            'student_id' => $student->id, 'created_by_user_id' => User::factory()->create()->id,
            'appointment_type' => 'initial_counseling', 'unit' => 'GCU',
            'appointment_date' => now()->toDateString(), 'start_time' => '09:00', 'end_time' => '10:00',
            'status' => 'completed', 'request_status' => 'completed',
        ]);

        Sanctum::actingAs($student, [], 'student');

        $res = $this->getJson('/api/student/dashboard')->assertOk();
        $json = $res->json();

        foreach (($json['referrals'] ?? []) as $r) {
            $this->assertNoFeedbackFields($r);
        }
        foreach (($json['appointments'] ?? []) as $a) {
            if (isset($a['referral'])) {
                $this->assertNoFeedbackFields($a['referral']);
            }
        }
    }

    public function test_show_testing_record_hides_feedback_slip(): void
    {
        $student = $this->student();
        $case = CaseFile::factory()->create(['student_id' => $student->id]);
        $referral = $this->feedbackReferral($student, $case);
        $record = TestingRecord::create([
            'case_id' => $case->id, 'student_id' => $student->id,
            'referred_by_user_id' => User::factory()->create()->id, 'status' => 'pending',
        ]);
        $record->update(['referral_id' => $referral->id]);

        Sanctum::actingAs($student, [], 'student');

        $res = $this->getJson("/api/student/testing-records/{$record->id}")->assertOk();
        $json = $res->json();
        if (isset($json['referral'])) {
            $this->assertNoFeedbackFields($json['referral']);
        }
    }
}