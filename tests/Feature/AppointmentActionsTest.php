<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\Student;
use App\Models\StaffAvailability;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

// Requirements 18 (set appointment schedule), 22 (detect conflicts), 24
// (cancel appointment) and 15/16 (student self-booking) - confirm(),
// cancel(), checkConflict()/checkConflictByStudent(), availability(), and
// the student self-booking trio had no dedicated tests before. index/show/
// check-in/reschedule/override-status are already covered elsewhere.
class AppointmentActionsTest extends TestCase
{
    use RefreshDatabase;

    private function gcuStaff(): User
    {
        return User::factory()->create(['role' => 'gcu_staff', 'is_active' => true]);
    }

    // now()->addDay() lands on a weekend depending on what day tests run -
    // several endpoints under test specifically reject weekend dates, so a
    // stable weekday keeps those assertions meaningful regardless of today.
    private function nextWeekday(): \Carbon\Carbon
    {
        return \Carbon\Carbon::parse('next monday');
    }

    private function appointment(array $attrs = []): Appointment
    {
        $student = $attrs['student_id'] ?? Student::factory()->create()->id;
        $caseId = $attrs['case_id'] ?? (CaseFile::where('student_id', $student)->first()?->id
            ?? CaseFile::factory()->create(['student_id' => $student])->id);
        return Appointment::create(array_merge([
            'appointment_code'   => 'APT-' . uniqid(),
            'case_id'            => $caseId,
            'student_id'         => $student,
            'created_by_user_id' => $this->gcuStaff()->id,
            'unit'               => 'GCU',
            'appointment_type'   => 'initial_counseling',
            'appointment_date'   => $this->nextWeekday()->toDateString(),
            'start_time'         => '09:00',
            'end_time'           => '10:00',
            'status'             => 'pending',
            'request_status'     => 'pending_confirmation',
        ], $attrs));
    }

    // #18: confirm() - sets status, assigns staff, checks conflicts and availability.
    public function test_confirm_sets_status_and_notifies_the_student(): void
    {
        Notification::fake();
        $appt = $this->appointment();
        $this->actingAs($this->gcuStaff());

        $this->postJson("/api/appointments/{$appt->id}/confirm")->assertOk();

        $appt->refresh();
        $this->assertSame('confirmed', $appt->status);
        $this->assertSame('confirmed', $appt->request_status);
        $this->assertTrue((bool) $appt->confirmation_sent);
        Notification::assertSentTo($appt->student, \App\Notifications\AppointmentConfirmedNotification::class);
    }

    public function test_confirm_rejects_a_conflicting_staff_assignment(): void
    {
        $staff = $this->gcuStaff();
        $this->appointment(['staff_user_id' => $staff->id, 'start_time' => '09:00', 'end_time' => '10:00', 'status' => 'confirmed']);
        $appt = $this->appointment(['start_time' => '09:30', 'end_time' => '10:30']);
        $this->actingAs($staff);

        $this->postJson("/api/appointments/{$appt->id}/confirm", ['staff_user_id' => $staff->id])
            ->assertStatus(422);
    }

    public function test_confirm_rejects_a_staff_member_outside_their_configured_availability(): void
    {
        $staff = $this->gcuStaff();
        $appt = $this->appointment(['appointment_date' => \Carbon\Carbon::parse('next monday')->toDateString(), 'start_time' => '09:00', 'end_time' => '10:00']);
        StaffAvailability::create([
            'user_id' => $staff->id, 'day_of_week' => 'Monday',
            'start_time' => '13:00', 'end_time' => '17:00', 'is_available' => true, 'unit' => 'GCU',
        ]);
        $this->actingAs($this->gcuStaff());

        $this->postJson("/api/appointments/{$appt->id}/confirm", ['staff_user_id' => $staff->id])
            ->assertStatus(422)
            ->assertJson(['message' => 'This staff member is not available at the selected date/time. Ask the student to reschedule.']);
    }

    // #24: cancel - also moves a scheduled/in-progress referral's status to cancelled.
    public function test_cancel_requires_a_reason_and_records_who_cancelled(): void
    {
        $appt = $this->appointment();
        $staff = $this->gcuStaff();
        $this->actingAs($staff);

        $this->postJson("/api/appointments/{$appt->id}/cancel")->assertStatus(422);

        $this->postJson("/api/appointments/{$appt->id}/cancel", ['cancellation_reason' => 'Student requested reschedule.'])
            ->assertOk();

        $appt->refresh();
        $this->assertSame('cancelled', $appt->status);
        $this->assertSame($staff->id, $appt->cancelled_by_user_id);
    }

    public function test_cancel_is_scoped_to_the_staffs_own_unit(): void
    {
        $tmduAppt = $this->appointment(['unit' => 'TMDU']);
        $gcu = $this->gcuStaff();

        $this->actingAs($gcu)
            ->postJson("/api/appointments/{$tmduAppt->id}/cancel", ['cancellation_reason' => 'test'])
            ->assertStatus(403);
    }

    public function test_cancelling_moves_a_scheduled_referral_to_cancelled(): void
    {
        $student = Student::factory()->create();
        $case = CaseFile::factory()->create(['student_id' => $student->id]);
        $referral = Referral::factory()->create(['student_id' => $student->id, 'case_id' => $case->id, 'status' => 'scheduled']);
        $appt = $this->appointment(['student_id' => $student->id, 'case_id' => $case->id]);
        $this->actingAs($this->gcuStaff());

        $this->postJson("/api/appointments/{$appt->id}/cancel", ['cancellation_reason' => 'No longer needed.'])->assertOk();

        $this->assertSame('cancelled', $referral->fresh()->status);
    }

    // #22: conflict detection.
    public function test_check_conflict_detects_an_overlap_for_a_staff_member(): void
    {
        $staff = $this->gcuStaff();
        $this->appointment(['staff_user_id' => $staff->id, 'start_time' => '09:00', 'end_time' => '10:00', 'status' => 'confirmed']);
        $this->actingAs($staff);

        $res = $this->postJson('/api/appointments/check-conflict', [
            'staff_user_id' => $staff->id,
            'appointment_date' => $this->nextWeekday()->toDateString(),
            'start_time' => '09:30', 'end_time' => '10:30',
        ])->assertOk();

        $this->assertTrue($res->json('has_conflict'));
    }

    public function test_check_conflict_blocks_a_holiday_date(): void
    {
        $staff = $this->gcuStaff();
        $holidayDate = $this->nextWeekday();
        \App\Models\CalendarEntry::create([
            'name' => 'Test Holiday', 'type' => 'holiday', 'status' => 'active',
            'entry_date' => $holidayDate->toDateString(),
        ]);
        $this->actingAs($staff);

        $res = $this->postJson('/api/appointments/check-conflict', [
            'staff_user_id' => $staff->id,
            'appointment_date' => $holidayDate->toDateString(),
            'start_time' => '09:00', 'end_time' => '10:00',
        ])->assertOk();

        $this->assertTrue($res->json('has_conflict'));
        $this->assertTrue($res->json('blocked'));
    }

    // #17/#20-adjacent: staff availability + booked slots for a day.
    public function test_availability_returns_configured_hours_and_booked_slots(): void
    {
        $staff = $this->gcuStaff();
        $date = \Carbon\Carbon::parse('next monday');
        StaffAvailability::create([
            'user_id' => $staff->id, 'day_of_week' => 'Monday',
            'start_time' => '08:00', 'end_time' => '16:00', 'is_available' => true, 'unit' => 'GCU',
        ]);
        $this->appointment(['staff_user_id' => $staff->id, 'appointment_date' => $date->toDateString(), 'status' => 'confirmed']);
        $this->actingAs($staff);

        $res = $this->getJson('/api/appointments/availability?user_id=' . $staff->id . '&date=' . $date->toDateString())->assertOk();

        $this->assertSame('08:00', $res->json('availability.start_time'));
        $this->assertCount(1, $res->json('booked_slots'));
    }

    // #15/#16: student self-booking.
    public function test_student_can_self_book_and_see_their_own_appointments(): void
    {
        $student = Student::factory()->create();
        Sanctum::actingAs($student, [], 'student');

        $this->postJson('/api/student/appointments', [
            'concern' => 'Need to talk to a counselor.',
            'appointment_date' => $this->nextWeekday()->toDateString(),
            'start_time' => '09:00', 'end_time' => '10:00',
        ])->assertStatus(201);

        $this->getJson('/api/student/appointments')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_student_self_booking_rejects_a_weekend_date(): void
    {
        $student = Student::factory()->create();
        $nextSaturday = \Carbon\Carbon::parse('next saturday');
        Sanctum::actingAs($student, [], 'student');

        $this->postJson('/api/student/appointments', [
            'concern' => 'Need help.',
            'appointment_date' => $nextSaturday->toDateString(),
            'start_time' => '09:00', 'end_time' => '10:00',
        ])->assertStatus(422)->assertJsonValidationErrors(['appointment_date']);
    }

    public function test_student_self_booking_rejects_an_already_taken_slot(): void
    {
        $this->appointment(['unit' => 'GCU', 'start_time' => '09:00', 'end_time' => '10:00', 'status' => 'confirmed']);
        $student = Student::factory()->create();
        Sanctum::actingAs($student, [], 'student');

        $this->postJson('/api/student/appointments', [
            'concern' => 'Need help.',
            'appointment_date' => $this->nextWeekday()->toDateString(),
            'start_time' => '09:00', 'end_time' => '10:00',
        ])->assertStatus(422)->assertJson(['message' => 'That time slot is already taken.']);
    }

    public function test_student_check_conflict_reports_blocked_and_taken_slots(): void
    {
        $this->appointment(['unit' => 'GCU', 'start_time' => '09:00', 'end_time' => '10:00', 'status' => 'confirmed']);
        $student = Student::factory()->create();
        Sanctum::actingAs($student, [], 'student');

        $res = $this->postJson('/api/student/appointments/check-conflict', [
            'appointment_date' => $this->nextWeekday()->toDateString(),
            'start_time' => '09:00', 'end_time' => '10:00',
        ])->assertOk();

        $this->assertTrue($res->json('has_conflict'));
    }
}