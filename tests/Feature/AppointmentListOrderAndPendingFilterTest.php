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
//      for first. Reverted on 11/5, then refined twice more the same day.
//      A date-ascending secondary sort was tried and then dropped again
//      per request - the order is purely by status group now (ties break
//      on newest-created first, same as before any of this). Final group
//      order: Awaiting Confirmation, Confirmed, Awaiting Student (not yet
//      expired), No-Show, Rescheduled, Expired, Cancelled, Completed.
//      Expired is its own group (an Awaiting Student row whose scheduling
//      link already lapsed - see isApptExpired() in appointments/Index.vue
//      and the Status filter's own "Expired" option), separate from the
//      still-live Awaiting Student rows above it.
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

    public function test_full_group_order(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $pendingConfirmation = $this->appointment(['status' => 'pending', 'request_status' => 'pending_confirmation']);
        $confirmed = $this->appointment(['status' => 'confirmed', 'request_status' => 'confirmed']);
        $awaiting  = $this->appointment(['status' => 'pending', 'request_status' => 'awaiting_student']);
        $noShow    = $this->appointment(['status' => 'no_show', 'request_status' => 'confirmed']);
        $rescheduled = $this->appointment(['status' => 'rescheduled', 'request_status' => 'confirmed']);
        $expired   = $this->appointment(['status' => 'pending', 'request_status' => 'awaiting_student', 'token_expires_at' => now()->subDay()]);
        $cancelled = $this->appointment(['status' => 'cancelled', 'request_status' => 'confirmed']);
        $completed = $this->appointment(['status' => 'completed', 'request_status' => 'confirmed']);

        $ids = collect($this->getJson('/api/appointments')->assertOk()->json('data'))->pluck('id')->all();
        $pos = fn ($a) => array_search($a->id, $ids);

        $order = [$pendingConfirmation, $confirmed, $awaiting, $noShow, $rescheduled, $expired, $cancelled, $completed];
        for ($i = 0; $i < count($order) - 1; $i++) {
            $this->assertLessThan($pos($order[$i + 1]), $pos($order[$i]), "Row " . $i . " should sort above row " . ($i + 1) . ".");
        }
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