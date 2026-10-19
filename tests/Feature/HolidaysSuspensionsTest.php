<?php

namespace Tests\Feature;

use App\Models\CalendarEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HolidaysSuspensionsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $u = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($u);
        return $u;
    }

    private function make(string $date, string $type = 'holiday', string $status = 'active'): CalendarEntry
    {
        return CalendarEntry::create(['entry_date' => $date, 'name' => 'Sample Day', 'type' => $type, 'status' => $status]);
    }

    public function test_add_and_duplicate_rule(): void
    {
        $this->admin();
        $d = now()->addDays(10)->toDateString();
        $this->postJson('/api/management/calendar-entries', ['entry_date' => $d, 'name' => 'Test Holiday', 'type' => 'holiday'])->assertCreated();
        $this->postJson('/api/management/calendar-entries', ['entry_date' => $d, 'name' => 'Another', 'type' => 'holiday'])->assertStatus(422);
        // different type on the same date is allowed
        $this->postJson('/api/management/calendar-entries', ['entry_date' => $d, 'name' => 'Suspension', 'type' => 'class_suspension'])->assertCreated();
        $this->postJson('/api/management/calendar-entries', ['entry_date' => $d, 'name' => 'X1', 'type' => 'bogus'])->assertStatus(422);
    }

    public function test_only_admin_can_write(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'gcu_staff']));
        $this->postJson('/api/management/calendar-entries', ['entry_date' => now()->addDay()->toDateString(), 'name' => 'Nope', 'type' => 'holiday'])->assertStatus(403);
    }

    public function test_archive_needs_allowed_reason_and_edit_is_blocked_after(): void
    {
        $this->admin();
        $e = $this->make(now()->addDays(5)->toDateString());
        $this->postJson("/api/management/calendar-entries/{$e->id}/archive", [])->assertStatus(422);
        $this->postJson("/api/management/calendar-entries/{$e->id}/archive", ['reason' => 'term_ended'])->assertStatus(422);
        $this->postJson("/api/management/calendar-entries/{$e->id}/archive", ['reason' => 'date_changed'])->assertOk();
        $this->putJson("/api/management/calendar-entries/{$e->id}", ['entry_date' => $e->entry_date->toDateString(), 'name' => 'Edited', 'type' => 'holiday'])->assertStatus(422);
        $this->assertNull(CalendarEntry::blocking($e->entry_date->toDateString()));
    }

    public function test_reactivate_only_for_today_or_later_and_no_duplicates(): void
    {
        $this->admin();
        $past = $this->make(now()->subDays(3)->toDateString(), 'holiday', 'archived');
        $this->postJson("/api/management/calendar-entries/{$past->id}/reactivate", ['reason' => 'offered_again'])->assertStatus(422);

        $d = now()->addDays(4)->toDateString();
        $old = $this->make($d, 'holiday', 'archived');
        $this->make($d, 'holiday', 'active');
        $this->postJson("/api/management/calendar-entries/{$old->id}/reactivate", ['reason' => 'offered_again'])->assertStatus(422);
    }

    public function test_delete_rules(): void
    {
        $this->admin();
        $future = $this->make(now()->addDays(8)->toDateString());
        $this->deleteJson("/api/management/calendar-entries/{$future->id}", [])->assertStatus(422);
        $this->deleteJson("/api/management/calendar-entries/{$future->id}", ['reason' => 'created_error'])->assertOk();
        $this->assertNull(CalendarEntry::find($future->id));

        $past = $this->make(now()->subDay()->toDateString());
        $this->deleteJson("/api/management/calendar-entries/{$past->id}", ['reason' => 'created_error'])->assertStatus(422);
        $this->assertNotNull(CalendarEntry::find($past->id));
    }

    public function test_upcoming_date_with_appointments_needs_acknowledgement(): void
    {
        $this->admin();
        $d = now()->addDays(6)->toDateString();
        $e = $this->make($d);
        $student = \App\Models\Student::factory()->create();
        \Illuminate\Support\Facades\DB::table('appointments')->insert([
            'student_id' => $student->id,
            'appointment_code' => 'APT-T1', 'unit' => 'GCU', 'appointment_type' => 'counseling_session',
            'appointment_date' => $d, 'start_time' => '09:00:00', 'end_time' => '10:00:00',
            'status' => 'scheduled', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->postJson("/api/management/calendar-entries/{$e->id}/archive", ['reason' => 'date_changed'])->assertStatus(422);
        $this->postJson("/api/management/calendar-entries/{$e->id}/archive", ['reason' => 'date_changed', 'acknowledged' => true])->assertOk();
    }
}