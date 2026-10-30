<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\CaseFile;
use App\Models\Student;
use App\Models\User;
use App\Notifications\AppointmentRescheduledDirectlyNotification;
use App\Notifications\NoShowEscalationNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * FR 2.6 / requirement #27 (mark missed) and #29 (update status):
 * permanent regression coverage for the three appointment-action methods
 * (rescheduleNow, escalateNoShow, overrideStatus). Prior session summary
 * listed them as "Pending"; the controller methods exist and were verified
 * by hand - these tests lock their behavior in so they can't silently
 * regress.
 */
class AppointmentRescheduleEscalateOverrideTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    private function user(string $role, array $extra = []): User
    {
        return User::factory()->create(array_merge(['role' => $role, 'is_active' => true], $extra));
    }

    private function student(string $college = 'CIT'): Student
{
    return Student::factory()->create(['college' => $college]);
}

    private function nextWeekday(): string
    {
        return now()->startOfDay()->next(Carbon::MONDAY)->toDateString();
    }

    private function appointment(string $unit = 'GCU', array $extra = []): Appointment
    {
        $student = $this->student();
        $case = CaseFile::factory()->create(['student_id' => $student->id]);

        return Appointment::factory()->create(array_merge([
            'student_id'       => $student->id,
            'case_id'          => $case->id,
            'unit'             => $unit,
            'status'           => 'confirmed',
            'request_status'   => 'confirmed',
            'appointment_date' => $this->nextWeekday(),
            'start_time'       => '09:00',
            'end_time'         => '10:00',
            'appointment_type' => 'initial_counseling',
        ], $extra));
    }

    // ===================== rescheduleNow =====================

    public function test_gcu_staff_can_reschedule_an_appointment_now(): void
{
        $staff = $this->user('gcu_staff');
        $appointment = $this->appointment('GCU');
        Sanctum::actingAs($staff);

        $oldDate = $appointment->appointment_date->format('Y-m-d');
        $newDate = now()->startOfDay()->next(Carbon::MONDAY)->addWeek()->toDateString();

        $this->postJson("/api/appointments/{$appointment->id}/reschedule-now", [
            'appointment_date'  => $newDate,
            'start_time'        => '11:00',
            'end_time'          => '12:00',
            'reschedule_reason' => 'Staff needs to move it.',
        ])->assertOk();

        $fresh = $appointment->fresh();
        $this->assertSame($newDate, $fresh->appointment_date->format('Y-m-d'));
        $this->assertSame('11:00', substr($fresh->start_time, 0, 5));
        $this->assertSame('confirmed', $fresh->status);
        $this->assertSame(1, $fresh->times_rescheduled);
        $this->assertSame(1, $fresh->slotHistory()->count(), 'Previous slot must be recorded.');
        $this->assertSame($oldDate, $fresh->slotHistory()->first()->slot_date->format('Y-m-d'));

        Notification::assertSentTo($fresh->student, AppointmentRescheduledDirectlyNotification::class);
    }

    public function test_reschedule_now_rejects_cross_unit_access(): void
    {
        $sdu = $this->user('sdu_head');
        $appointment = $this->appointment('GCU');
        Sanctum::actingAs($sdu);

        $this->postJson("/api/appointments/{$appointment->id}/reschedule-now", [
            'appointment_date' => $this->nextWeekday(),
            'start_time'       => '11:00',
            'end_time'         => '12:00',
        ])->assertStatus(403);
    }

    public function test_reschedule_now_rejects_weekend(): void
    {
        $staff = $this->user('gcu_staff');
        $appointment = $this->appointment('GCU');
        Sanctum::actingAs($staff);

        $this->postJson("/api/appointments/{$appointment->id}/reschedule-now", [
            'appointment_date' => now()->next(Carbon::SATURDAY)->toDateString(),
            'start_time'       => '09:00',
            'end_time'         => '10:00',
        ])->assertStatus(422)->assertJsonValidationErrors(['appointment_date']);
    }

    public function test_reschedule_now_rejects_outside_office_hours(): void
    {
        $staff = $this->user('gcu_staff');
        $appointment = $this->appointment('GCU');
        Sanctum::actingAs($staff);

        $this->postJson("/api/appointments/{$appointment->id}/reschedule-now", [
            'appointment_date' => $this->nextWeekday(),
            'start_time'       => '06:00',
            'end_time'         => '07:00',
        ])->assertStatus(422);
    }

    public function test_reschedule_now_rejects_past_date(): void
    {
        $staff = $this->user('gcu_staff');
        $appointment = $this->appointment('GCU');
        Sanctum::actingAs($staff);

        $this->postJson("/api/appointments/{$appointment->id}/reschedule-now", [
            'appointment_date' => '2020-01-01', // a past Wednesday
            'start_time'       => '09:00',
            'end_time'         => '10:00',
        ])->assertStatus(422);
    }

    public function test_reschedule_now_rejects_unit_conflict(): void
    {
        $staff = $this->user('gcu_staff');
        $appointment = $this->appointment('GCU');
        // A different GCU appointment already sits in the target slot.
        $this->appointment('GCU', [
            'appointment_date' => $this->nextWeekday(),
            'start_time'       => '13:00',
            'end_time'         => '14:00',
        ]);
        Sanctum::actingAs($staff);

        $this->postJson("/api/appointments/{$appointment->id}/reschedule-now", [
            'appointment_date' => $this->nextWeekday(),
            'start_time'       => '13:00',
            'end_time'         => '14:00',
        ])->assertStatus(422);
    }

    // ===================== escalateNoShow =====================

    public function test_escalate_no_show_with_call_slip(): void
    {
        $staff = $this->user('gcu_staff');
        $appointment = $this->appointment('GCU', ['status' => 'confirmed', 'request_status' => 'confirmed']);
        // A Dean in the student's college so notifyDeanSecretaries has a target.
        $dean = User::factory()->create(['role' => 'dean', 'college' => $appointment->student->college, 'is_active' => true]);
        Sanctum::actingAs($staff);

        $this->postJson("/api/appointments/{$appointment->id}/escalate-no-show", ['action' => 'call_slip'])
            ->assertOk();

        $fresh = $appointment->fresh();
        $this->assertSame('no_show', $fresh->status);
        $this->assertTrue($fresh->no_show_escalated);
        $this->assertNotNull($fresh->no_show_escalated_at);
        $this->assertSame('pending', $fresh->call_slip_stage);
        $this->assertSame(1, $fresh->case->fresh()->no_show_count);

        Notification::assertSentTo($dean, NoShowEscalationNotification::class);
    }

    public function test_escalate_no_show_with_reschedule_action(): void
    {
        $staff = $this->user('gcu_staff');
        $appointment = $this->appointment('GCU', ['status' => 'confirmed']);
        Sanctum::actingAs($staff);

        $this->postJson("/api/appointments/{$appointment->id}/escalate-no-show", ['action' => 'reschedule'])
            ->assertOk();

        $fresh = $appointment->fresh();
        $this->assertSame('no_show', $fresh->status);
        $this->assertFalse((bool) $fresh->no_show_escalated, 'reschedule action must not mark escalation.');
        $this->assertSame(1, $fresh->case->fresh()->no_show_count);
    }

    public function test_escalate_no_show_refuses_repeat_escalation(): void
    {
        $staff = $this->user('gcu_staff');
        $appointment = $this->appointment('GCU', [
            'status'            => 'no_show',
            'no_show_escalated' => true,
            'no_show_escalated_at' => now(),
        ]);
        Sanctum::actingAs($staff);

        $this->postJson("/api/appointments/{$appointment->id}/escalate-no-show", ['action' => 'call_slip'])
            ->assertStatus(422);
    }

    public function test_escalate_no_show_rejects_cross_unit_access(): void
    {
        $sdu = $this->user('sdu_head');
        $appointment = $this->appointment('GCU');
        Sanctum::actingAs($sdu);

        $this->postJson("/api/appointments/{$appointment->id}/escalate-no-show", ['action' => 'call_slip'])
            ->assertStatus(403);
    }

    // ===================== overrideStatus =====================

    public function test_gcu_staff_can_override_status(): void
    {
        $staff = $this->user('gcu_staff');
        $appointment = $this->appointment('GCU', ['status' => 'pending', 'request_status' => 'pending_confirmation']);
        Sanctum::actingAs($staff);

        $this->postJson("/api/appointments/{$appointment->id}/override-status", [
            'target' => 'confirmed',
            'reason' => 'Student called to confirm.',
        ])->assertOk();

        $fresh = $appointment->fresh();
        $this->assertSame('confirmed', $fresh->status);
        $this->assertSame('confirmed', $fresh->request_status);
    }

    public function test_override_status_rejects_pending_for_non_tmdu(): void
    {
        $staff = $this->user('gcu_staff');
        $appointment = $this->appointment('GCU');
        Sanctum::actingAs($staff);

        $this->postJson("/api/appointments/{$appointment->id}/override-status", [
            'target' => 'pending',
            'reason' => 'Try to revert.',
        ])->assertStatus(422);
    }

    public function test_override_status_requires_reason(): void
    {
        $staff = $this->user('gcu_staff');
        $appointment = $this->appointment('GCU', ['status' => 'pending']);
        Sanctum::actingAs($staff);

        $this->postJson("/api/appointments/{$appointment->id}/override-status", [
            'target' => 'confirmed',
        ])->assertStatus(422)->assertJsonValidationErrors(['reason']);
    }

    public function test_override_status_no_show_syncs_case_no_show_count(): void
    {
        $staff = $this->user('gcu_staff');
        $appointment = $this->appointment('GCU', ['status' => 'confirmed']);
        Sanctum::actingAs($staff);
        $case = $appointment->case;

        $this->assertSame(0, (int) $case->fresh()->no_show_count);

        // confirmed -> no_show increments.
        $this->postJson("/api/appointments/{$appointment->id}/override-status", [
            'target' => 'no_show',
            'reason' => 'Student never showed up.',
        ])->assertOk();
        $this->assertSame(1, (int) $case->fresh()->no_show_count);

        // no_show -> confirmed decrements back to 0.
        $this->postJson("/api/appointments/{$appointment->id}/override-status", [
            'target' => 'confirmed',
            'reason' => 'Correction: student did attend.',
        ])->assertOk();
        $this->assertSame(0, (int) $case->fresh()->no_show_count);
    }

    public function test_override_status_cancelled_sets_audit_fields(): void
    {
        $staff = $this->user('gcu_staff');
        $appointment = $this->appointment('GCU');
        Sanctum::actingAs($staff);

        $this->postJson("/api/appointments/{$appointment->id}/override-status", [
            'target' => 'cancelled',
            'reason' => 'Referral withdrawn by student.',
        ])->assertOk();

        $fresh = $appointment->fresh();
        $this->assertSame('cancelled', $fresh->status);
        $this->assertNotNull($fresh->cancelled_at);
        $this->assertSame($staff->id, $fresh->cancelled_by_user_id);
        $this->assertSame('Referral withdrawn by student.', $fresh->cancellation_reason);
    }

    public function test_override_status_completed_marks_checked_in(): void
    {
        $staff = $this->user('gcu_staff');
        $appointment = $this->appointment('GCU');
        Sanctum::actingAs($staff);

        $this->postJson("/api/appointments/{$appointment->id}/override-status", [
            'target' => 'completed',
            'reason' => 'Late check-in recorded by staff.',
        ])->assertOk();

        $fresh = $appointment->fresh();
        $this->assertSame('completed', $fresh->status);
        $this->assertTrue((bool) $fresh->checked_in);
        $this->assertNotNull($fresh->checked_in_at);
    }
}