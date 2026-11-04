<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\CaseFile;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

// B#, 11/4/2026 - 11/5/2026: GCU Appointments list fixes.
//   1) Row order used to put Confirmed first, then everything still
//      awaiting action, then Completed last. Flipped on 11/4 so the
//      still-awaiting-action rows sorted first - but that buried Confirmed
//      next to Completed at the bottom, when it's what staff actually look
//      for first. Reverted on 11/5, then refined again the same day:
//      Awaiting Confirmation (status=pending, request_status=
//      pending_confirmation) always needs a staff decision before anything
//      else can happen, so it now sorts above even Confirmed. Then
//      Confirmed, then everything else still awaiting something
//      (awaiting_student/no_show/rescheduled/cancelled), then Completed
//      last. Within each group, rows now sort by appointment date/time
//      ascending (soonest first) instead of newest-created first.
//   2) The "Pending" dropdown filter used to only match status=pending AND
//      request_status=pending_confirmation, excluding Awaiting-Student rows
//      even though they're also status=pending. Reverted to match the
//      plain status column again, so "Pending" counts Awaiting-Student too.
class AppointmentListOrderAndPendingFilterTest extends TestCase
{
    use RefreshDatabase;

    private function nextWeekday(): string
    {
        return now()->startOfDay()->next(Carbon::MONDAY)->toDateString();
    }

    private function appointment(array $extra = []): Appointment
    {
        $student = Student::factory()->create();
        $case = CaseFile::factory()->create(['student_id' => $student->id]);

        return Appointment::factory()->create(array_merge([
            'student_id'       => $student->id,
            'case_id'          => $case->id,
            'unit'             => 'GCU',
            'appointment_date' => $this->nextWeekday(),
            'start_time'       => '09:00',
            'end_time'         => '10:00',
            'appointment_type' => 'initial_counseling',
        ], $extra));
    }

    public function test_awaiting_confirmation_sorts_above_confirmed_above_other_awaiting_above_completed(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $confirmed = $this->appointment(['status' => 'confirmed', 'request_status' => 'confirmed']);
        $completed = $this->appointment(['status' => 'completed', 'request_status' => 'confirmed']);
        $awaiting  = $this->appointment(['status' => 'pending', 'request_status' => 'awaiting_student']);
        $pendingConfirmation = $this->appointment(['status' => 'pending', 'request_status' => 'pending_confirmation']);

        $ids = collect($this->getJson('/api/appointments')->assertOk()->json('data'))->pluck('id')->all();

        $posAwaiting  = array_search($awaiting->id, $ids);
        $posPending   = array_search($pendingConfirmation->id, $ids);
        $posConfirmed = array_search($confirmed->id, $ids);
        $posCompleted = array_search($completed->id, $ids);

        $this->assertLessThan($posConfirmed, $posPending, 'Awaiting-Confirmation should sort above Confirmed.');
        $this->assertLessThan($posAwaiting, $posConfirmed, 'Confirmed should sort above Awaiting-Student.');
        $this->assertLessThan($posCompleted, $posAwaiting, 'Awaiting-Student should sort above Completed.');
        $this->assertLessThan($posCompleted, $posPending, 'Awaiting-Confirmation should sort above Completed.');
    }

    public function test_within_a_status_group_earliest_appointment_sorts_first(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $later   = $this->appointment(['status' => 'confirmed', 'request_status' => 'confirmed', 'appointment_date' => now()->addDays(6)->toDateString()]);
        $earlier = $this->appointment(['status' => 'confirmed', 'request_status' => 'confirmed', 'appointment_date' => now()->addDays(5)->toDateString()]);

        $ids = collect($this->getJson('/api/appointments')->assertOk()->json('data'))->pluck('id')->all();

        $this->assertLessThan(array_search($later->id, $ids), array_search($earlier->id, $ids), 'The earlier-dated Confirmed appointment should sort above the later-dated one.');
    }

    public function test_pending_filter_counts_awaiting_student_rows_too(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $awaiting            = $this->appointment(['status' => 'pending', 'request_status' => 'awaiting_student']);
        $pendingConfirmation = $this->appointment(['status' => 'pending', 'request_status' => 'pending_confirmation']);
        $confirmed           = $this->appointment(['status' => 'confirmed', 'request_status' => 'confirmed']);

        $res = $this->getJson('/api/appointments?status=pending')->assertOk();
        $ids = collect($res->json('data'))->pluck('id')->all();

        $this->assertContains($awaiting->id, $ids);
        $this->assertContains($pendingConfirmation->id, $ids);
        $this->assertNotContains($confirmed->id, $ids);
    }
}