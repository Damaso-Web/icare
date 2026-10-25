<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

// Bug (10/25/2026): a gcu_staff counselor's dashboard (Open Cases,
// Appointments Today, Upcoming Appointments) counted the whole GCU unit
// regardless of who was logged in, so a counselor with nothing assigned to
// them yet still saw a full dashboard - clicking through to the actual
// Student Information Files / Appointments lists (already scoped to their
// own caseload per B623/B625) showed none of it. Only the GCU Head (admin)
// should keep unit-wide numbers. Pending Referrals/Recent Referrals are
// deliberately left out of this test - that's a shared inbox of
// not-yet-assigned referrals, same as the unscoped Referral Queue.
class GCUDashboardCaseloadScopeTest extends TestCase
{
    use RefreshDatabase;

    private function openCase(?int $counselorId): CaseFile
    {
        $student = Student::factory()->create(['is_active' => true]);
        $case = CaseFile::factory()->create([
            'student_id' => $student->id, 'current_unit' => 'GCU', 'status' => 'open',
            'primary_counselor_id' => $counselorId,
        ]);
        Referral::factory()->create(['student_id' => $student->id, 'case_id' => $case->id, 'acknowledged_at' => now()]);
        return $case;
    }

    // appointment_date is passed as the today() Carbon instance itself (not
    // ->toDateString()) - matching how the rest of the app and its existing
    // tests write this column, so what gets stored lines up with what
    // DashboardController's own `where('appointment_date', today())` compares
    // against.
    private function appointmentFor(?CaseFile $case): Appointment
    {
        $student = $case?->student ?? Student::factory()->create();
        $staff = User::factory()->create(['role' => 'gcu_staff']);
        return Appointment::create([
            'appointment_code'    => 'APT-' . uniqid(),
            'case_id'             => $case?->id,
            'student_id'          => $student->id,
            'staff_user_id'       => $staff->id,
            'created_by_user_id'  => $staff->id,
            'unit'                => 'GCU',
            'appointment_type'    => 'initial_counseling',
            'appointment_date'    => today(),
            'start_time'          => '09:00',
            'end_time'            => '10:00',
            'status'              => 'confirmed',
            'request_status'      => 'pending_confirmation',
        ]);
    }

    public function test_gcu_staff_sees_only_their_own_caseload_on_the_dashboard(): void
    {
        $counselor = User::factory()->create(['role' => 'gcu_staff']);
        $other     = User::factory()->create(['role' => 'gcu_staff']);

        $mine   = $this->openCase($counselor->id);
        $theirs = $this->openCase($other->id);
        $this->appointmentFor($mine);
        $this->appointmentFor($theirs);

        Sanctum::actingAs($counselor);
        $dash = $this->getJson('/api/dashboard')->assertOk()->json();

        $this->assertSame(1, $dash['stats']['open_cases']);
        $this->assertSame(1, $dash['stats']['appointments_today']);
        $this->assertCount(1, $dash['upcoming_appointments']);
    }

    public function test_gcu_staff_appointments_still_include_an_unassigned_cases_appointment(): void
    {
        // An unassigned case (no primary_counselor_id yet) doesn't show on
        // the gcu_staff's Open Cases card either - same as it's excluded
        // from their own Student Information Files list (CaseController
        // scopes strictly to primary/previous counselor, B623). But its
        // appointment still counts, matching AppointmentController's wider
        // "case null or counselor null" scope (B625).
        $counselor = User::factory()->create(['role' => 'gcu_staff']);
        $unassigned = $this->openCase(null);
        $this->appointmentFor($unassigned);

        Sanctum::actingAs($counselor);
        $dash = $this->getJson('/api/dashboard')->assertOk()->json();

        $this->assertSame(0, $dash['stats']['open_cases']);
        $this->assertSame(1, $dash['stats']['appointments_today']);
    }

    public function test_admin_keeps_unit_wide_dashboard_numbers(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $a = $this->openCase(User::factory()->create(['role' => 'gcu_staff'])->id);
        $b = $this->openCase(User::factory()->create(['role' => 'gcu_staff'])->id);
        $this->appointmentFor($a);
        $this->appointmentFor($b);

        Sanctum::actingAs($admin);
        $dash = $this->getJson('/api/dashboard')->assertOk()->json();

        $this->assertSame(2, $dash['stats']['open_cases']);
        $this->assertSame(2, $dash['stats']['appointments_today']);
    }

    public function test_pending_referrals_inbox_stays_unit_wide_for_gcu_staff(): void
    {
        $counselor = User::factory()->create(['role' => 'gcu_staff']);
        $student = Student::factory()->create();
        $case = CaseFile::factory()->create(['student_id' => $student->id, 'current_unit' => 'GCU']);
        // A 'submitted' referral has no counselor yet - it's everyone's inbox.
        Referral::factory()->create(['student_id' => $student->id, 'case_id' => $case->id, 'status' => 'submitted']);

        Sanctum::actingAs($counselor);
        $dash = $this->getJson('/api/dashboard')->assertOk()->json();

        $this->assertSame(1, $dash['stats']['pending_referrals']);
    }
}