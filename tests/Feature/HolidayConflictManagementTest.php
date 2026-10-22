<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Student;
use App\Models\User;
use App\Notifications\AppointmentHolidayCancelledNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

// B534: marking a date as a holiday/suspension when appointments already
// exist on it must detect them, block new bookings on that date, let the
// admin pick how the existing ones are handled, and keep an audit trail.
class HolidayConflictManagementTest extends TestCase
{
    use RefreshDatabase;

    private function nextWeekday(int $plusDays = 5): string
    {
        $d = now()->addDays($plusDays);
        while ($d->isWeekend()) $d->addDay();
        return $d->toDateString();
    }

    private function appt(Student $s, string $date, ?int $staffId = null): Appointment
    {
        return Appointment::create([
            'student_id' => $s->id, 'staff_user_id' => $staffId ?? User::factory()->create(['role' => 'gcu_staff'])->id,
            'unit' => 'GCU', 'appointment_type' => 'initial_counseling',
            'appointment_date' => $date, 'start_time' => '09:00', 'end_time' => '10:00', 'duration_minutes' => 60,
            'status' => 'confirmed', 'request_status' => 'confirmed',
        ]);
    }

    public function test_creating_a_holiday_with_existing_appointments_needs_a_resolution_first(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $d = $this->nextWeekday();
        $s = Student::factory()->create();
        $a = $this->appt($s, $d);

        $res = $this->postJson('/api/management/calendar-entries', ['entry_date' => $d, 'name' => 'Surprise Holiday', 'type' => 'holiday'])
            ->assertStatus(409);
        $res->assertJsonPath('needs_resolution', true)->assertJsonPath('conflict_count', 1);
        $this->assertDatabaseMissing('calendar_entries', ['entry_date' => $d]);
        $this->assertNull($a->fresh()->holiday_conflict_flagged_at);
    }

    public function test_auto_cancel_cancels_and_notifies_the_student(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);
        $d = $this->nextWeekday();
        $s = Student::factory()->create();
        $a = $this->appt($s, $d);

        $this->postJson('/api/management/calendar-entries', ['entry_date' => $d, 'name' => 'Surprise Holiday', 'type' => 'holiday', 'resolution' => 'auto_cancel'])
            ->assertCreated()->assertJsonPath('resolved_conflicts', 1);

        $a->refresh();
        $this->assertSame('cancelled', $a->status);
        $this->assertSame('auto_cancelled', $a->holiday_conflict_resolution);
        $this->assertNotNull($a->holiday_conflict_flagged_at);
        $this->assertNotNull($a->holiday_conflict_resolved_at);
        Notification::assertSentTo($s, AppointmentHolidayCancelledNotification::class);
        $this->assertTrue(AuditLog::where('action', 'holiday_conflict_cancelled')->exists());
    }

    public function test_auto_reschedule_moves_to_the_next_open_business_day_same_staff(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);
        $staff = User::factory()->create(['role' => 'gcu_staff']);
        $d = $this->nextWeekday();
        $s = Student::factory()->create();
        $a = $this->appt($s, $d, $staff->id);

        $this->postJson('/api/management/calendar-entries', ['entry_date' => $d, 'name' => 'Surprise Holiday', 'type' => 'holiday', 'resolution' => 'auto_reschedule'])
            ->assertCreated()->assertJsonPath('resolved_conflicts', 1);

        $a->refresh();
        $this->assertNotEquals($d, $a->appointment_date->format('Y-m-d'));
        $this->assertFalse($a->appointment_date->isWeekend());
        $this->assertSame('confirmed', $a->status); // never cancelled, just moved
        $this->assertSame('auto_rescheduled', $a->holiday_conflict_resolution);
        $this->assertSame(1, $a->slotHistory()->count());
        $this->assertTrue(AuditLog::where('action', 'holiday_conflict_rescheduled')->exists());
    }

    public function test_manual_review_just_flags_the_appointment(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);
        $d = $this->nextWeekday();
        $s = Student::factory()->create();
        $a = $this->appt($s, $d);

        $this->postJson('/api/management/calendar-entries', ['entry_date' => $d, 'name' => 'Surprise Holiday', 'type' => 'holiday', 'resolution' => 'manual_review'])
            ->assertCreated();

        $a->refresh();
        $this->assertSame('confirmed', $a->status);
        $this->assertNull($a->holiday_conflict_resolution);
        $this->assertNotNull($a->holiday_conflict_flagged_at);
        $this->assertSame('Action Required: Holiday Conflict', $a->holiday_conflict_label);
    }

    public function test_a_holiday_with_no_existing_appointments_is_created_without_any_prompt(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $d = $this->nextWeekday();
        $this->postJson('/api/management/calendar-entries', ['entry_date' => $d, 'name' => 'Quiet Holiday', 'type' => 'holiday'])
            ->assertCreated()->assertJsonPath('resolved_conflicts', 0);
    }

    public function test_new_bookings_are_blocked_once_the_date_is_an_active_holiday(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $d = $this->nextWeekday();
        $this->postJson('/api/management/calendar-entries', ['entry_date' => $d, 'name' => 'Quiet Holiday', 'type' => 'holiday'])->assertCreated();

        $staff = User::factory()->create(['role' => 'gcu_staff']);
        $s = Student::factory()->create();
        Sanctum::actingAs($staff);
        $this->postJson('/api/appointments/check-conflict', [
            'appointment_date' => $d, 'start_time' => '09:00', 'end_time' => '10:00', 'staff_user_id' => $staff->id,
        ])->assertOk()->assertJsonPath('blocked', true);
    }
}