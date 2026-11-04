<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bug (11/5/2026): a student scheduling their appointment via
 * PublicSchedulingController::submit() (and the staff-side equivalents,
 * AppointmentController::confirm()/scheduleForStudent()) advanced
 * $appointment->case->latestReferral - the case's most RECENTLY CREATED
 * referral - instead of the referral THIS appointment actually belongs to.
 *
 * A case can carry more than one referral (e.g. an older Class Attendance
 * referral plus a newer, unrelated referral). Picking a schedule for the
 * OLDER referral's appointment was wrongly jumping the NEWER referral
 * straight to "scheduled" - skipping "acknowledged" entirely, with no
 * staff action on it at all, even though nobody had touched it.
 *
 * These tests pin down that the correct, appointment-specific referral
 * is the one that moves, and the unrelated one is left untouched.
 */
class ScheduleAdvancesCorrectReferralTest extends TestCase
{
    use RefreshDatabase;

    private function nextWeekday(): string
    {
        return now()->startOfDay()->next(\Carbon\Carbon::MONDAY)->toDateString();
    }

    private function caseWithTwoReferrals(): array
    {
        $student = Student::factory()->create();
        $case = CaseFile::factory()->create(['student_id' => $student->id]);

        // The older referral - this is the one the appointment under test
        // actually belongs to.
        $oldReferral = Referral::factory()->create([
            'student_id'     => $student->id,
            'case_id'        => $case->id,
            'referral_type'  => 'class_attendance',
            'status'         => 'acknowledged',
        ]);

        // A newer, unrelated referral on the SAME case - this is the one
        // that must NOT move, since the appointment being scheduled has
        // nothing to do with it.
        $newReferral = Referral::factory()->create([
            'student_id'     => $student->id,
            'case_id'        => $case->id,
            'referral_type'  => 'counseling',
            'status'         => 'submitted',
        ]);

        return [$case, $oldReferral, $newReferral];
    }

    public function test_public_submit_advances_the_appointments_own_referral_not_the_cases_latest(): void
    {
        [$case, $oldReferral, $newReferral] = $this->caseWithTwoReferrals();

        $appointment = Appointment::factory()->create([
            'case_id'          => $case->id,
            'student_id'       => $case->student_id,
            'referral_id'      => $oldReferral->id,
            'scheduling_token' => 'tok-' . uniqid(),
            'token_expires_at' => now()->addDays(7),
            'status'           => 'pending',
            'request_status'   => 'awaiting_student',
        ]);

        $this->postJson("/api/schedule/{$appointment->scheduling_token}/submit", [
            'appointment_date' => $this->nextWeekday(),
            'start_time'       => '09:00',
            'end_time'         => '10:00',
        ])->assertOk();

        $this->assertSame('scheduled', $oldReferral->fresh()->status);
        $this->assertSame('submitted', $newReferral->fresh()->status);
    }

    public function test_public_scheduling_show_returns_the_appointments_own_referral(): void
    {
        [$case, $oldReferral, $newReferral] = $this->caseWithTwoReferrals();

        $appointment = Appointment::factory()->create([
            'case_id'          => $case->id,
            'student_id'       => $case->student_id,
            'referral_id'      => $oldReferral->id,
            'scheduling_token' => 'tok-' . uniqid(),
            'token_expires_at' => now()->addDays(7),
            'status'           => 'pending',
            'request_status'   => 'awaiting_student',
        ]);

        $this->getJson("/api/schedule/{$appointment->scheduling_token}")
            ->assertOk()
            ->assertJsonPath('referral.id', $oldReferral->id);
    }
}