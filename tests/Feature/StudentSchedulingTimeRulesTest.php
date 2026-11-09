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

        $res->assertStatus(422)->assertJson(['message' => 'This time has already passed. Please choose another date or time.']);

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
        ])->assertStatus(422)->assertJson(['message' => 'This time has already passed. Please choose another date or time.']);

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
        $this->assertSame('This time has already passed. Please choose another date or time.', $res->json('message'));

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
        ])->assertStatus(422)->assertJson(['message' => 'That time slot is already taken.']);
    }

    public function test_a_partially_overlapping_slot_is_still_a_hard_conflict_even_with_room_in_the_cap(): void
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

        $otherStudent = Student::factory()->create();
        Sanctum::actingAs($otherStudent, [], 'student');
        $this->postJson('/api/student/appointments', [
            'concern'          => 'Different time, still overlaps.',
            'appointment_date' => $date,
            'start_time'       => '09:30',
            'end_time'         => '10:30',
        ])->assertStatus(422)->assertJson(['message' => 'That time slot is already taken.']);
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
        ])->assertStatus(422)->assertJson(['message' => 'This time slot is no longer available. Please pick another.']);
    }
}