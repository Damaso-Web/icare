<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Scratch verification for the 11/3/2026 fix (and same-day revert) to
// AppointmentController::scheduleForStudent(): token_expires_at lapsing
// blocks the STUDENT's own self-service link (PublicSchedulingController::
// submit() already enforces that separately), but this endpoint is the
// staff-only override (role:admin,gcu_staff) - GCU/Admin must retain the
// ability to manually schedule an appointment even after the student's
// window has expired.
class ScheduleForStudentExpiryCheckTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    private function placeholder(string $tokenExpiresAt): Appointment
    {
        $student = Student::factory()->create();
        $case = CaseFile::factory()->create(['student_id' => $student->id, 'current_unit' => 'GCU']);
        $referral = Referral::factory()->create(['student_id' => $student->id, 'case_id' => $case->id, 'status' => 'acknowledged', 'acknowledged_at' => now()]);

        return Appointment::create([
            'appointment_code' => 'APT-EXP', 'case_id' => $case->id, 'referral_id' => $referral->id,
            'student_id' => $student->id, 'staff_user_id' => User::factory()->create(['role' => 'gcu_staff'])->id,
            'created_by_user_id' => $this->admin()->id, 'appointment_type' => 'initial_counseling', 'unit' => 'GCU',
            'scheduling_token' => 'tok-exp', 'token_expires_at' => $tokenExpiresAt,
            'request_status' => 'awaiting_student', 'status' => 'pending',
            // Placeholder values the student hasn't actually chosen yet -
            // same shape as ensureSchedulingAppointment()'s own placeholder.
            'appointment_date' => \Carbon\Carbon::parse('next monday')->toDateString(),
            'start_time' => '08:00', 'end_time' => '09:00',
        ]);
    }

    public function test_staff_can_still_schedule_an_expired_appointment(): void
    {
        $appointment = $this->placeholder(now()->subDay()->toDateTimeString());

        $res = $this->actingAs($this->admin())
            ->postJson("/api/appointments/{$appointment->id}/schedule-student", [
                'appointment_date' => \Carbon\Carbon::parse('next monday')->toDateString(),
                'start_time' => '09:00',
                'end_time' => '10:00',
            ]);

        $res->assertStatus(200);
        $this->assertSame('pending_confirmation', $appointment->fresh()->request_status);
    }

    public function test_scheduling_still_succeeds_while_the_token_is_still_valid(): void
    {
        $appointment = $this->placeholder(now()->addDays(7)->toDateTimeString());

        $res = $this->actingAs($this->admin())
            ->postJson("/api/appointments/{$appointment->id}/schedule-student", [
                'appointment_date' => \Carbon\Carbon::parse('next monday')->toDateString(),
                'start_time' => '09:00',
                'end_time' => '10:00',
            ]);

        $res->assertStatus(200);
        $this->assertSame('pending_confirmation', $appointment->fresh()->request_status);
    }
}