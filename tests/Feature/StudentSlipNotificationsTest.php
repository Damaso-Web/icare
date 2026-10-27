<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\Student;
use App\Models\User;
use App\Notifications\AdmissionSlipIssuedNotification;
use App\Notifications\FeedbackSlipSentNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

// B113/B114: issuing an Admission Slip or sending a Feedback Slip only ever
// told the referrer - the student themselves got no word that one existed,
// and no way to see it. Both actions now also notify the student.
class StudentSlipNotificationsTest extends TestCase
{
    use RefreshDatabase;

    // An "unlocked" referral: acknowledged, with a completed appointment -
    // canEditSif() (shared by both saveAdmissionSlip() and sendFeedback())
    // requires both.
    private function unlockedReferral(string $type = 'class_attendance'): Referral
    {
        $student = Student::factory()->create();
        $case = CaseFile::factory()->create(['student_id' => $student->id]);
        $referral = Referral::factory()->create([
            'student_id' => $student->id, 'case_id' => $case->id,
            'referral_type' => $type, 'status' => 'acknowledged',
            'acknowledged_at' => now(),
        ]);
        Appointment::create([
            'referral_id' => $referral->id, 'case_id' => $case->id, 'student_id' => $student->id,
            'unit' => 'GCU', 'appointment_type' => 'counseling', 'appointment_date' => today(),
            'start_time' => '09:00', 'end_time' => '10:00', 'status' => 'completed',
        ]);
        return $referral->fresh();
    }

    public function test_student_notified_when_admission_slip_is_issued(): void
    {
        Notification::fake();
        $gcu = User::factory()->create(['role' => 'gcu_staff', 'is_active' => true]);
        Sanctum::actingAs($gcu);
        $referral = $this->unlockedReferral('class_attendance');

        $this->postJson("/api/referrals/{$referral->id}/admission-slip", [
            'admission_date' => today()->toDateString(), 'admission_excused' => true,
            'admission_details' => ['causes' => ['health'], 'actions' => ['counseling']],
        ])->assertOk();

        Notification::assertSentTo($referral->student, AdmissionSlipIssuedNotification::class);
    }

    public function test_student_notified_when_feedback_slip_is_sent(): void
    {
        Notification::fake();
        $gcu = User::factory()->create(['role' => 'gcu_staff', 'is_active' => true]);
        Sanctum::actingAs($gcu);
        $referral = $this->unlockedReferral('counseling');

        $this->postJson("/api/referrals/{$referral->id}/feedback", [
            'feedback_notes' => 'Met with the student twice.',
            'feedback_checklist' => ['counseling'],
        ])->assertOk();

        Notification::assertSentTo($referral->student, FeedbackSlipSentNotification::class);
    }
}