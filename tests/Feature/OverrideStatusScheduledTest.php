<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Regression test for: "Override Referral Status -> Scheduled" reporting a
// false "This time slot conflicts with another appointment for this unit"
// error, and (before the fix) silently rescheduling the wrong referral's
// appointment when a student had more than one referral on the same case.
//
// Root cause: ReferralController::overrideStatus()'s 'scheduled' branch
// looked up "the appointment waiting for this student" without scoping the
// query to the referral actually being overridden (unlike the equivalent
// logic in ensureSchedulingAppointment()/acknowledge()). On a case with more
// than one referral, it could find and mutate a DIFFERENT referral's
// appointment instead of the current one's.
class OverrideStatusScheduledTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    public function test_overriding_a_fresh_never_acknowledged_referral_straight_to_scheduled_succeeds(): void
    {
        $student = Student::factory()->create();
        $case = CaseFile::factory()->create(['student_id' => $student->id, 'current_unit' => 'GCU']);
        $referral = Referral::factory()->create([
            'student_id' => $student->id, 'case_id' => $case->id, 'status' => 'submitted',
        ]);

        $this->assertSame(0, Appointment::count(), 'precondition: no appointments exist at all');

        $res = $this->actingAs($this->admin())
            ->postJson("/api/referrals/{$referral->id}/override-status", [
                'status' => 'scheduled',
                'reason' => 'for research',
                'appointment_date' => \Carbon\Carbon::parse('next monday')->toDateString(),
                'start_time' => '13:47',
                'end_time' => '15:47',
            ]);

        $res->assertStatus(200);
        $this->assertSame('scheduled', $referral->fresh()->status);

        // A placeholder appointment should have been auto-created and then
        // scheduled for THIS referral, since it had none before.
        $this->assertSame(1, Appointment::count());
        $appointment = Appointment::first();
        $this->assertSame($referral->id, $appointment->referral_id);
        $this->assertSame('13:47', substr($appointment->start_time, 0, 5));
        $this->assertSame('15:47', substr($appointment->end_time, 0, 5));
    }

    public function test_overriding_to_scheduled_does_not_touch_an_older_sibling_referrals_appointment(): void
    {
        $student = Student::factory()->create();
        $case = CaseFile::factory()->create(['student_id' => $student->id, 'current_unit' => 'GCU']);

        // An OLDER referral on the same case, already acknowledged earlier -
        // this has its own awaiting_student placeholder appointment, scoped
        // to ITS OWN referral_id (see ensureSchedulingAppointment/acknowledge()).
        $olderReferral = Referral::factory()->create([
            'student_id' => $student->id, 'case_id' => $case->id, 'status' => 'acknowledged', 'acknowledged_at' => now(),
        ]);
        $olderPlaceholder = Appointment::create([
            'appointment_code' => 'APT-OLD', 'case_id' => $case->id, 'referral_id' => $olderReferral->id,
            'student_id' => $student->id, 'staff_user_id' => User::factory()->create(['role' => 'gcu_staff'])->id,
            'created_by_user_id' => $this->admin()->id, 'appointment_type' => 'initial_counseling', 'unit' => 'GCU',
            'scheduling_token' => 'tok-old', 'token_expires_at' => now()->addDays(7),
            'request_status' => 'awaiting_student', 'status' => 'pending',
            'appointment_date' => \Carbon\Carbon::parse('next monday')->toDateString(),
            'start_time' => '08:00', 'end_time' => '09:00',
        ]);

        // The CURRENT referral - still 'submitted', never acknowledged, has
        // NO appointment of its own.
        $currentReferral = Referral::factory()->create([
            'student_id' => $student->id, 'case_id' => $case->id, 'status' => 'submitted',
        ]);

        $res = $this->actingAs($this->admin())
            ->postJson("/api/referrals/{$currentReferral->id}/override-status", [
                'status' => 'scheduled',
                'reason' => 'for research',
                'appointment_date' => \Carbon\Carbon::parse('next monday')->toDateString(),
                'start_time' => '13:47',
                'end_time' => '15:47',
            ]);

        $res->assertStatus(200);
        $this->assertSame($currentReferral->id, $res->json('referral.id'));
        $this->assertSame('scheduled', $res->json('referral.status'));

        // The CURRENT referral is the one that changed.
        $this->assertSame('scheduled', $currentReferral->fresh()->status);

        // The OLDER sibling referral and its appointment must be untouched.
        $this->assertSame('acknowledged', $olderReferral->fresh()->status);
        $olderPlaceholder->refresh();
        $this->assertSame('08:00', substr($olderPlaceholder->start_time, 0, 5));
        $this->assertSame('09:00', substr($olderPlaceholder->end_time, 0, 5));
        $this->assertSame('awaiting_student', $olderPlaceholder->request_status);
        $this->assertSame('pending', $olderPlaceholder->status);

        // A SECOND, distinct appointment should now exist, scoped to the
        // current referral, with the requested time - not a hijacked reuse
        // of the older referral's appointment.
        $this->assertSame(2, Appointment::count());
        $newAppointment = Appointment::where('referral_id', $currentReferral->id)->first();
        $this->assertNotNull($newAppointment, 'a new appointment scoped to the current referral should have been created');
        $this->assertNotSame($olderPlaceholder->id, $newAppointment->id);
        $this->assertSame('13:47', substr($newAppointment->start_time, 0, 5));
        $this->assertSame('15:47', substr($newAppointment->end_time, 0, 5));
    }

    public function test_overriding_to_scheduled_when_the_referrals_own_placeholder_already_exists_reuses_it(): void
    {
        $student = Student::factory()->create();
        $case = CaseFile::factory()->create(['student_id' => $student->id, 'current_unit' => 'GCU']);
        $referral = Referral::factory()->create([
            'student_id' => $student->id, 'case_id' => $case->id, 'status' => 'acknowledged', 'acknowledged_at' => now(),
        ]);
        $placeholder = Appointment::create([
            'appointment_code' => 'APT-OWN', 'case_id' => $case->id, 'referral_id' => $referral->id,
            'student_id' => $student->id, 'staff_user_id' => User::factory()->create(['role' => 'gcu_staff'])->id,
            'created_by_user_id' => $this->admin()->id, 'appointment_type' => 'initial_counseling', 'unit' => 'GCU',
            'scheduling_token' => 'tok-own', 'token_expires_at' => now()->addDays(7),
            'request_status' => 'awaiting_student', 'status' => 'pending',
            'appointment_date' => \Carbon\Carbon::parse('next monday')->toDateString(),
            'start_time' => '08:00', 'end_time' => '09:00',
        ]);

        $res = $this->actingAs($this->admin())
            ->postJson("/api/referrals/{$referral->id}/override-status", [
                'status' => 'scheduled',
                'reason' => 'for research',
                'appointment_date' => \Carbon\Carbon::parse('next monday')->toDateString(),
                'start_time' => '13:47',
                'end_time' => '15:47',
            ]);

        $res->assertStatus(200);
        $this->assertSame('scheduled', $referral->fresh()->status);

        // Its OWN existing placeholder should be reused (updated), not
        // duplicated.
        $this->assertSame(1, Appointment::count());
        $placeholder->refresh();
        $this->assertSame('13:47', substr($placeholder->start_time, 0, 5));
        $this->assertSame('15:47', substr($placeholder->end_time, 0, 5));
    }
}