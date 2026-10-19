<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\CalendarEntry;
use App\Models\Student;
use App\Models\User;
use App\Notifications\AppointmentRescheduledNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RescheduleAndBlockedDatesTest extends TestCase
{
    use RefreshDatabase;

    private function nextWeekday(int $plusDays = 3): string
    {
        $d = now()->addDays($plusDays);
        while ($d->isWeekend()) $d->addDay();
        return $d->toDateString();
    }

    private function appt(Student $s, string $date, string $start = '09:00', string $end = '10:00'): Appointment
    {
        return Appointment::create([
            'student_id' => $s->id, 'staff_user_id' => User::factory()->create(['role' => 'gcu_staff'])->id,
            'unit' => 'GCU', 'appointment_type' => 'initial_counseling',
            'appointment_date' => $date, 'start_time' => $start, 'end_time' => $end, 'duration_minutes' => 60,
            'status' => 'confirmed', 'request_status' => 'confirmed',
        ]);
    }

    public function test_staff_reschedule_updates_the_same_appointment_and_notifies_student(): void
    {
        Notification::fake();
        Sanctum::actingAs(User::factory()->create(['role' => 'gcu_staff', 'unit' => 'GCU']));
        $s = Student::factory()->create();
        $a = $this->appt($s, $this->nextWeekday());

        $this->postJson("/api/appointments/{$a->id}/reschedule", ['reschedule_reason' => 'the counselor is unavailable'])->assertOk();

        $this->assertSame(1, Appointment::count());               // no duplicate record
        $a->refresh();
        $this->assertSame('awaiting_student', $a->request_status);
        $this->assertSame(1, $a->times_rescheduled);
        $this->assertSame(1, $a->slotHistory()->count());
        Notification::assertSentTo($s, AppointmentRescheduledNotification::class, function ($n) use ($s) {
            return str_contains($n->toArray($s)['message'], 'rescheduled due to the counselor is unavailable')
                && str_ends_with($n->toArray($s)['link'], '/student/appointments/' . $n->appointment->id);
        });
    }

    public function test_previous_slots_stay_unavailable_after_repeated_reschedules(): void
    {
        Notification::fake();
        $s = Student::factory()->create();
        $d1 = $this->nextWeekday(3);
        $a = $this->appt($s, $d1, '09:00', '10:00');
        Sanctum::actingAs(User::factory()->create(['role' => 'gcu_staff', 'unit' => 'GCU']));

        $this->postJson("/api/appointments/{$a->id}/reschedule", ['reschedule_reason' => 'first change'])->assertOk();
        $token = $a->fresh()->scheduling_token;
        $d2 = $this->nextWeekday(5);
        $this->postJson("/api/schedule/{$token}/submit", ['appointment_date' => $d2, 'start_time' => '13:00', 'end_time' => '14:00'])->assertOk();

        $this->postJson("/api/appointments/{$a->id}/reschedule", ['reschedule_reason' => 'second change'])->assertOk();
        $token = $a->fresh()->scheduling_token;
        $this->assertSame(2, $a->fresh()->slotHistory()->count());

        // both earlier slots are refused, by the availability check and by the submit itself
        foreach ([[$d1, '09:00', '10:00'], [$d2, '13:00', '14:00']] as [$d, $st, $en]) {
            $this->postJson("/api/schedule/{$token}/check-availability", ['appointment_date' => $d, 'start_time' => $st, 'end_time' => $en])
                ->assertOk()->assertJsonPath('available', false);
            $this->postJson("/api/schedule/{$token}/submit", ['appointment_date' => $d, 'start_time' => $st, 'end_time' => $en])->assertStatus(422);
        }
        $this->postJson("/api/schedule/{$token}/submit", ['appointment_date' => $d1, 'start_time' => '11:00', 'end_time' => '12:00'])->assertOk();
    }

    public function test_blocked_dates_are_rejected_everywhere_and_archived_ones_are_not(): void
    {
        $date = $this->nextWeekday(6);
        $entry = CalendarEntry::create(['entry_date' => $date, 'name' => 'Test Holiday', 'type' => 'holiday', 'status' => 'active']);
        $s = Student::factory()->create();

        // student self-booking
        Sanctum::actingAs($s, [], 'student');
        $this->postJson('/api/student/appointments/check-conflict', ['appointment_date' => $date, 'start_time' => '09:00', 'end_time' => '10:00'])
            ->assertOk()->assertJsonPath('blocked', true);
        $this->postJson('/api/student/appointments', ['concern' => 'x', 'appointment_date' => $date, 'start_time' => '09:00', 'end_time' => '10:00'])->assertStatus(422);

        // staff booking
        $staff = User::factory()->create(['role' => 'gcu_staff', 'unit' => 'GCU']);
        Sanctum::actingAs($staff);
        $case = \App\Models\CaseFile::factory()->create(['student_id' => $s->id]);
        $payload = ['case_id' => $case->id, 'student_id' => $s->id, 'unit' => 'GCU', 'appointment_type' => 'follow_up_session',
            'appointment_date' => $date, 'start_time' => '09:00', 'end_time' => '10:00'];
        $this->postJson('/api/appointments', $payload)->assertStatus(422)->assertJsonPath('errors.appointment_date.0', fn($m) => str_contains($m, 'Holiday'));

        // archived entries no longer block
        $entry->update(['status' => 'archived']);
        $this->postJson('/api/appointments', $payload)->assertCreated();
    }

    public function test_public_calendar_marks_blocked_days_with_a_label(): void
    {
        $date = $this->nextWeekday(4);
        CalendarEntry::create(['entry_date' => $date, 'name' => "All Saints' Day", 'type' => 'holiday', 'status' => 'active']);
        $s = Student::factory()->create();
        $a = $this->appt($s, $this->nextWeekday(8));
        $a->update(['request_status' => 'awaiting_student', 'status' => 'pending', 'scheduling_token' => 'tok123', 'token_expires_at' => now()->addDay()]);

        $days = $this->getJson('/api/schedule/tok123/month-availability?month=' . substr($date, 0, 7))->assertOk()->json('days');
        $row = collect($days)->firstWhere('date', $date);
        $this->assertSame('blocked', $row['status']);
        $this->assertStringContainsString("All Saints' Day", $row['label']);

        $this->postJson('/api/schedule/tok123/submit', ['appointment_date' => $date, 'start_time' => '09:00', 'end_time' => '10:00'])->assertStatus(422);
        $this->getJson('/api/schedule/tok123/blocked-dates?from=' . $date . '&to=' . $date)->assertOk()->assertJsonPath("blocked.{$date}.type", 'holiday');
    }
}