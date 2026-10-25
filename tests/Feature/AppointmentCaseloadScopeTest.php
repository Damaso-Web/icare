<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\CaseFile;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

// B625 reverted (10/25/2026): a gcu_staff counselor is back to seeing/
// acting on every GCU appointment, not just their own caseload - matching
// B623's revert on Student Information Files. TMDU Testing Results (B624)
// is unaffected and still scoped per-counselor.
class AppointmentCaseloadScopeTest extends TestCase
{
    use RefreshDatabase;

    private function appointment(?User $counselor, ?CaseFile $case = null): Appointment
    {
        $student = Student::factory()->create();
        $case ??= CaseFile::factory()->create(['student_id' => $student->id, 'primary_counselor_id' => $counselor?->id]);
        $staff = User::factory()->create(['role' => 'gcu_staff']);

        return Appointment::create([
            'appointment_code'    => 'APT-' . uniqid(),
            'case_id'             => $case->id,
            'student_id'          => $student->id,
            'staff_user_id'       => $staff->id,
            'created_by_user_id'  => $staff->id,
            'unit'                => 'GCU',
            'appointment_type'    => 'initial_counseling',
            'appointment_date'    => now()->addDay()->toDateString(),
            'start_time'          => '09:00',
            'end_time'            => '10:00',
            'status'              => 'pending',
        ]);
    }

    public function test_gcu_staff_sees_and_opens_appointments_outside_their_own_caseload(): void
    {
        $counselor = User::factory()->create(['role' => 'gcu_staff']);
        $mine = $this->appointment($counselor);
        $other = $this->appointment(User::factory()->create(['role' => 'gcu_staff']));

        Sanctum::actingAs($counselor);
        $list = $this->getJson('/api/appointments')->assertOk()->json('data');
        $ids = collect($list)->pluck('id')->all();
        $this->assertContains($mine->id, $ids);
        $this->assertContains($other->id, $ids);

        $this->getJson("/api/appointments/{$mine->id}")->assertOk();
        $this->getJson("/api/appointments/{$other->id}")->assertOk();
    }

    public function test_gcu_staff_can_still_see_an_unassigned_cases_appointment(): void
    {
        $counselor = User::factory()->create(['role' => 'gcu_staff']);
        $unassigned = $this->appointment(null);

        Sanctum::actingAs($counselor);
        $list = $this->getJson('/api/appointments')->assertOk()->json('data');
        $this->assertTrue(collect($list)->contains('id', $unassigned->id));
        $this->getJson("/api/appointments/{$unassigned->id}")->assertOk();
    }

    public function test_admin_keeps_unit_wide_visibility(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $a = $this->appointment(User::factory()->create(['role' => 'gcu_staff']));
        $b = $this->appointment(User::factory()->create(['role' => 'gcu_staff']));

        Sanctum::actingAs($admin);
        $list = $this->getJson('/api/appointments')->assertOk()->json('data');
        $ids = collect($list)->pluck('id')->all();
        $this->assertContains($a->id, $ids);
        $this->assertContains($b->id, $ids);
        $this->getJson("/api/appointments/{$a->id}")->assertOk();
    }
}