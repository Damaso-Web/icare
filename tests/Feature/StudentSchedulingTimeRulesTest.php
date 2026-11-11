<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

// Panel fix (11/8/2026): a student picking their own appointment time -
// whether via a public scheduling link (PublicSchedulingController) or
// while logged in (AppointmentController::storeByStudent/
// checkConflictByStudent) - could previously submit a time on today's date
// that had already passed, or a slot of any length (no 1-2 hour bound).
// Both surfaces now share the same two rules.
class StudentSchedulingTimeRulesTest extends TestCase
{
    use RefreshDatabase;

    private function nextWeekday(): Carbon
    {
        return now()->startOfDay()->next(Carbon::MONDAY);
    }

    // --- Public scheduling link ---

    private function linkedAppointment(): Appointment
    {
        return Appointment::factory()->create([
            'scheduling_token' => 'valid-token-xyz',
            'token_expires_at' => now()->addDays(7),
            'status'           => 'pending',
            'request_status'   => 'awaiting_student',
        ]);
    }

    public function test_public_link_rejects_a_time_already_passed_today(): void
    {
        // Force "today" to a weekday with real time-of-day left, then ask
        // for a slot earlier than the current time.
        Carbon::setTestNow(Carbon::parse('next monday')->setTime(14, 0));

        $appointment = $this->linkedAppointment();

        $res = $this->postJson("/api/schedule/{$appointment->scheduling_token}/submit", [
            'appointment_date' => now()->toDateString(),
            'start_time'       => '09:00',
            'end_time'         => '10:00',
        ]);

        $res->assertStatus(422)->assertJson(['message' => 'This time has already passed (it is now 2:00 PM). Please choose a later time or another date.']);

        Carbon::setTestNow();
    }

    public function test_public_link_rejects_a_slot_shorter_than_one_hour(): void
    {
        $appointment = $this->linkedAppointment();

        $this->postJson("/api/schedule/{$appointment->scheduling_token}/submit", [
            'appointment_date' => $this->nextWeekday()->toDateString(),
            'start_time'       => '09:00',
            'end_time'         => '09:30',
        ])->assertStatus(422)->assertJson(['message' => 'The minimum appointment length is 1 hour.']);
    }

    public function test_public_link_rejects_a_slot_longer_than_two_hours(): void
    {
        $appointment = $this->linkedAppointment();

        $this->postJson("/api/schedule/{$appointment->scheduling_token}/submit", [
            'appointment_date' => $this->nextWeekday()->toDateString(),
            'start_time'       => '09:00',
            'end_time'         => '12:00',
        ])->assertStatus(422)->assertJson(['message' => 'The maximum appointment length is 2 hours.']);
    }

    public function test_public_link_accepts_a_valid_future_slot_within_bounds(): void
    {
        $appointment = $this->linkedAppointment();

        $this->postJson("/api/schedule/{$appointment->scheduling_token}/submit", [
            'appointment_date' => $this->nextWeekday()->toDateString(),
            'start_time'       => '09:00',
            'end_time'         => '11:00',
        ])->assertOk();
    }

    public function test_public_link_check_availability_also_flags_a_too_short_slot(): void
    {
        $appointment = $this->linkedAppointment();

        $res = $this->postJson("/api/schedule/{$appointment->scheduling_token}/check-availability", [
            'appointment_date' => $this->nextWeekday()->toDateString(),
            'start_time'       => '09:00',
            'end_time'         => '09:45',
        ])->assertOk();

        $this->assertFalse($res->json('available'));
        $this->assertSame('The minimum appointment length is 1 hour.', $res->json('message'));
    }

    // --- Logged-in student self-booking ---

    public function test_student_self_book_rejects_a_time_already_passed_today(): void
    {
        Carbon::setTestNow(Carbon::parse('next monday')->setTime(14, 0));

        $student = Student::factory()->create();
        Sanctum::actingAs($student, [], 'student');

        $this->postJson('/api/student/appointments', [
            'concern'          => 'Need help.',
            'appointment_date' => now()->toDateString(),
            'start_time'       => '09:00',
            'end_time'         => '10:00',
        ])->assertStatus(422)->assertJson(['message' => 'This time has already passed (it is now 2:00 PM). Please choose a later time or another date.']);

        Carbon::setTestNow();
    }

    public function test_student_self_book_rejects_a_slot_outside_one_to_two_hours(): void
    {
        $student = Student::factory()->create();
        Sanctum::actingAs($student, [], 'student');

        $this->postJson('/api/student/appointments', [
            'concern'          => 'Need help.',
            'appointment_date' => $this->nextWeekday()->toDateString(),
            'start_time'       => '09:00',
            'end_time'         => '09:30',
        ])->assertStatus(422)->assertJson(['message' => 'The minimum appointment length is 1 hour.']);
    }

    public function test_student_check_conflict_also_flags_a_passed_time(): void
    {
        Carbon::setTestNow(Carbon::parse('next monday')->setTime(14, 0));

        $student = Student::factory()->create();
        Sanctum::actingAs($student, [], 'student');

        $res = $this->postJson('/api/student/appointments/check-conflict', [
            'appointment_date' => now()->toDateString(),
            'start_time'       => '09:00',
            'end_time'         => '10:00',
        ])->assertOk();

        $this->assertTrue($res->json('has_conflict'));
        $this->assertSame('This time has already passed (it is now 2:00 PM). Please choose a later time or another date.', $res->json('message'));

        Carbon::setTestNow();
    }

    public function test_student_self_book_still_accepts_a_valid_future_slot(): void
    {
        $student = Student::factory()->create();
        Sanctum::actingAs($student, [], 'student');

        $this->postJson('/api/student/appointments', [
            'concern'          => 'Need help.',
            'appointment_date' => $this->nextWeekday()->toDateString(),
            'start_time'       => '09:00',
            'end_time'         => '10:30',
        ])->assertStatus(201);
    }

    // --- Item #8 (11/8/2026): up to 5 students can share one exact slot ---

    public function test_five_students_can_book_the_exact_same_slot_but_a_sixth_cannot(): void
    {
        $date = $this->nextWeekday()->toDateString();

        for ($i = 0; $i < 5; $i++) {
            $student = Student::factory()->create();
            Sanctum::actingAs($student, [], 'student');

            $this->postJson('/api/student/appointments', [
                'concern'          => "Need help #{$i}.",
                'appointment_date' => $date,
                'start_time'       => '09:00',
                'end_time'         => '10:00',
            ])->assertStatus(201);
        }

        $sixthStudent = Student::factory()->create();
        Sanctum::actingAs($sixthStudent, [], 'student');

        $this->postJson('/api/student/appointments', [
            'concern'          => 'Need help #6.',
            'appointment_date' => $date,
            'start_time'       => '09:00',
            'end_time'         => '10:00',
        ])->assertStatus(422)->assertJson(['message' => 'This time is full: 5 of 5 students are already booked during this time. Please choose another time.']);
    }

    public function test_a_partially_overlapping_time_is_accepted_while_fewer_than_five_students_overlap(): void
    {
        $date = $this->nextWeekday()->toDateString();

        $student = Student::factory()->create();
        Sanctum::actingAs($student, [], 'student');
        $this->postJson('/api/student/appointments', [
            'concern'          => 'Need help.',
            'appointment_date' => $date,
            'start_time'       => '09:00',
            'end_time'         => '10:00',
        ])->assertStatus(201);

        // A different start/end that overlaps is fine: only 1 student is
        // booked during 09:30-10:30, well under the 5 allowed at once.
        $otherStudent = Student::factory()->create();
        Sanctum::actingAs($otherStudent, [], 'student');
        $this->postJson('/api/student/appointments', [
            'concern'          => 'Different time, overlapping.',
            'appointment_date' => $date,
            'start_time'       => '09:30',
            'end_time'         => '10:30',
        ])->assertStatus(201);
    }

    public function test_a_partial_overlap_is_rejected_once_five_students_are_booked_at_the_same_moment(): void
    {
        $date = $this->nextWeekday()->toDateString();

        // Three at 09:00-10:00 and two at 09:30-10:30 -> five students are
        // booked together from 09:30 to 10:00.
        $times = [['09:00', '10:00'], ['09:00', '10:00'], ['09:00', '10:00'], ['09:30', '10:30'], ['09:30', '10:30']];
        foreach ($times as $i => [$start, $end]) {
            Sanctum::actingAs(Student::factory()->create(), [], 'student');
            $this->postJson('/api/student/appointments', [
                'concern'          => "Need help #{$i}.",
                'appointment_date' => $date,
                'start_time'       => $start,
                'end_time'         => $end,
            ])->assertStatus(201);
        }

        $sixth = Student::factory()->create();
        Sanctum::actingAs($sixth, [], 'student');

        // Touches the full stretch (09:30-10:00) -> rejected.
        $this->postJson('/api/student/appointments', [
            'concern'          => 'Lands in the full stretch.',
            'appointment_date' => $date,
            'start_time'       => '09:45',
            'end_time'         => '10:45',
        ])->assertStatus(422)->assertJson(['message' => 'This time is full: 5 of 5 students are already booked during this time. Please choose another time.']);

        // Starts after the full stretch ends (only the two 09:30 students overlap).
        $this->postJson('/api/student/appointments', [
            'concern'          => 'After the busy stretch.',
            'appointment_date' => $date,
            'start_time'       => '10:00',
            'end_time'         => '11:00',
        ])->assertStatus(201);
    }

    public function test_public_link_also_allows_five_students_on_the_exact_same_slot(): void
    {
        $date = $this->nextWeekday()->toDateString();

        // Five students, each with their own token, all booking 09:00-10:00.
        for ($i = 0; $i < 5; $i++) {
            $appointment = Appointment::factory()->create([
                'scheduling_token' => "cap-token-{$i}",
                'token_expires_at' => now()->addDays(7),
                'status'           => 'pending',
                'request_status'   => 'awaiting_student',
            ]);

            $this->postJson("/api/schedule/{$appointment->scheduling_token}/submit", [
                'appointment_date' => $date,
                'start_time'       => '09:00',
                'end_time'         => '10:00',
            ])->assertOk();
        }

        $sixthAppointment = Appointment::factory()->create([
            'scheduling_token' => 'cap-token-6',
            'token_expires_at' => now()->addDays(7),
            'status'           => 'pending',
            'request_status'   => 'awaiting_student',
        ]);

        $this->postJson("/api/schedule/{$sixthAppointment->scheduling_token}/submit", [
            'appointment_date' => $date,
            'start_time'       => '09:00',
            'end_time'         => '10:00',
        ])->assertStatus(422)->assertJson(['message' => 'This time is full: 5 of 5 students are already booked during this time. Please choose another time.']);
    }

    // --- Follow-up (11/10/2026): exact-minute booking + accurate full message ---

    public function test_choosing_the_exact_current_minute_is_accepted(): void
    {
        // 14:00:30 - the seconds used to make "14:00" count as already passed.
        Carbon::setTestNow(Carbon::parse('next monday')->setTime(14, 0, 30));

        $student = Student::factory()->create();
        Sanctum::actingAs($student, [], 'student');

        $res = $this->postJson('/api/student/appointments/check-conflict', [
            'appointment_date' => now()->toDateString(),
            'start_time'       => '14:00',
            'end_time'         => '15:00',
        ])->assertOk();
        $this->assertFalse($res->json('has_conflict'));

        $this->postJson('/api/student/appointments', [
            'concern'          => 'Need help right now.',
            'appointment_date' => now()->toDateString(),
            'start_time'       => '14:00',
            'end_time'         => '15:00',
        ])->assertStatus(201);

        Carbon::setTestNow();
    }

    public function test_public_link_accepts_the_exact_current_minute(): void
    {
        Carbon::setTestNow(Carbon::parse('next monday')->setTime(14, 0, 45));

        $appointment = $this->linkedAppointment();

        $res = $this->postJson("/api/schedule/{$appointment->scheduling_token}/check-availability", [
            'appointment_date' => now()->toDateString(),
            'start_time'       => '14:00',
            'end_time'         => '15:00',
        ])->assertOk();
        $this->assertTrue($res->json('available'));

        Carbon::setTestNow();
    }

    public function test_a_minute_that_has_ended_is_still_rejected_with_the_current_time(): void
    {
        Carbon::setTestNow(Carbon::parse('next monday')->setTime(14, 0, 30));

        $student = Student::factory()->create();
        Sanctum::actingAs($student, [], 'student');

        $res = $this->postJson('/api/student/appointments/check-conflict', [
            'appointment_date' => now()->toDateString(),
            'start_time'       => '13:59',
            'end_time'         => '14:59',
        ])->assertOk();

        $this->assertTrue($res->json('has_conflict'));
        $this->assertSame('This time has already passed (it is now 2:00 PM). Please choose a later time or another date.', $res->json('message'));

        Carbon::setTestNow();
    }

    public function test_availability_check_says_the_slot_is_full_with_the_count(): void
    {
        $date = $this->nextWeekday()->toDateString();

        for ($i = 0; $i < 5; $i++) {
            $student = Student::factory()->create();
            Sanctum::actingAs($student, [], 'student');
            $this->postJson('/api/student/appointments', [
                'concern'          => "Need help #{$i}.",
                'appointment_date' => $date,
                'start_time'       => '09:00',
                'end_time'         => '10:00',
            ])->assertStatus(201);
        }

        $sixth = Student::factory()->create();
        Sanctum::actingAs($sixth, [], 'student');

        $res = $this->postJson('/api/student/appointments/check-conflict', [
            'appointment_date' => $date,
            'start_time'       => '09:00',
            'end_time'         => '10:00',
        ])->assertOk();

        $this->assertTrue($res->json('has_conflict'));
        $this->assertSame('This time is full: 5 of 5 students are already booked during this time. Please choose another time.', $res->json('message'));
        $this->assertSame(5, $res->json('slot_count'));
        $this->assertSame(5, $res->json('slot_cap'));
    }

    public function test_public_link_availability_check_says_the_slot_is_full(): void
    {
        $date = $this->nextWeekday()->toDateString();

        for ($i = 0; $i < 5; $i++) {
            $appointment = Appointment::factory()->create([
                'scheduling_token' => "full-token-{$i}",
                'token_expires_at' => now()->addDays(7),
                'status'           => 'pending',
                'request_status'   => 'awaiting_student',
            ]);
            $this->postJson("/api/schedule/{$appointment->scheduling_token}/submit", [
                'appointment_date' => $date,
                'start_time'       => '09:00',
                'end_time'         => '10:00',
            ])->assertOk();
        }

        $sixth = $this->linkedAppointment();

        $res = $this->postJson("/api/schedule/{$sixth->scheduling_token}/check-availability", [
            'appointment_date' => $date,
            'start_time'       => '09:00',
            'end_time'         => '10:00',
        ])->assertOk();

        $this->assertFalse($res->json('available'));
        $this->assertSame('This time is full: 5 of 5 students are already booked during this time. Please choose another time.', $res->json('message'));
    }
}