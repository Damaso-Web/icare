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

// B#, 11/4/2026: two GCU Appointments list fixes.
//   1) Row order used to put Confirmed first, then everything still
//      awaiting action, then Completed last. Flipped: rows still awaiting
//      something (pending/awaiting_student/etc.) now sort first, Confirmed
//      sinks below them, Completed stays lowest of all.
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

    public function test_awaiting_rows_sort_above_confirmed_which_sorts_above_completed(): void
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

        $this->assertLessThan($posConfirmed, $posAwaiting, 'Awaiting-Student should sort above Confirmed.');
        $this->assertLessThan($posConfirmed, $posPending, 'Awaiting-Confirmation should sort above Confirmed.');
        $this->assertLessThan($posCompleted, $posConfirmed, 'Confirmed should sort above Completed.');
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