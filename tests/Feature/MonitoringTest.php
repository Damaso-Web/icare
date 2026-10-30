<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\CaseFile;
use App\Models\Complaint;
use App\Models\Referral;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MonitoringTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $role, array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role' => $role,
            'is_active' => true,
        ], $attributes));
    }

    private function appointment(string $unit, string $status = 'pending'): Appointment
    {
        $student = Student::factory()->create();
        $case = CaseFile::factory()->create([
            'student_id' => $student->id,
            'current_unit' => $unit,
        ]);

        return Appointment::create([
            'case_id' => $case->id,
            'student_id' => $student->id,
            'unit' => $unit,
            'appointment_type' => 'counseling',
            'appointment_date' => now()->addDays(2)->toDateString(),
            'start_time' => '09:00:00',
            'end_time' => '09:30:00',
            'duration_minutes' => 30,
            'status' => $status,
        ]);
    }

    private function caseFile(string $unit, string $status = 'open', bool $followUp = false): CaseFile
    {
        return CaseFile::factory()->create([
            'current_unit' => $unit,
            'status' => $status,
            'requires_follow_up' => $followUp,
        ]);
    }

    public function test_monitoring_endpoint_requires_authenticated_staff(): void
    {
        $this->getJson('/api/monitoring')->assertUnauthorized();
    }

    public function test_monitoring_endpoint_rejects_roles_without_monitoring_access(): void
    {
        Sanctum::actingAs($this->staff('faculty'));

        $this->getJson('/api/monitoring')->assertForbidden();
    }

    public function test_gcu_monitoring_returns_expected_sections_and_counts_only_gcu_work(): void
    {
        $user = $this->staff('gcu_staff');
        $gcuAppointment = $this->appointment('GCU');
        $this->appointment('TMDU');
        $gcuCase = $this->caseFile('GCU', 'open', true);
        $this->caseFile('TMDU', 'open', true);

        Sanctum::actingAs($user);
        $response = $this->getJson('/api/monitoring')->assertOk();

        $response->assertJsonStructure([
            'stats' => ['pending_referrals', 'pending_appointments', 'unresolved_cases', 'flagged_follow_up'],
            'pending_referrals' => ['data', 'current_page', 'per_page', 'total'],
            'pending_appointments' => ['data', 'current_page', 'per_page', 'total'],
            'unresolved_cases' => ['data', 'current_page', 'per_page', 'total'],
        ]);

        $this->assertSame(1, $response->json('stats.pending_appointments'));
        // 2: the appointment's own implicit case AND the explicit $gcuCase are
        // both open/GCU, so both count as unresolved. 1: only $gcuCase has
        // requires_follow_up=true (the appointment's case uses the factory default).
        $this->assertSame(2, $response->json('stats.unresolved_cases'));
        $this->assertSame(1, $response->json('stats.flagged_follow_up'));
        $this->assertSame([$gcuAppointment->id], collect($response->json('pending_appointments.data'))->pluck('id')->all());

        $unresolvedIds = collect($response->json('unresolved_cases.data'))->pluck('id')->all();
        $this->assertCount(2, $unresolvedIds);
        $this->assertContains($gcuCase->id, $unresolvedIds);
        $this->assertContains($gcuAppointment->case_id, $unresolvedIds);
    }

    public function test_tmdu_monitoring_only_includes_tmdu_appointments_and_cases(): void
    {
        $tmdu = $this->staff('tmdu_staff');
        $tmduAppointment = $this->appointment('TMDU');
        $this->appointment('GCU');
        $tmduCase = $this->caseFile('TMDU', 'in_progress');
        $this->caseFile('GCU', 'in_progress');

        Sanctum::actingAs($tmdu);
        $response = $this->getJson('/api/monitoring')->assertOk();

        $this->assertSame(1, $response->json('stats.pending_appointments'));
        $this->assertSame(2, $response->json('stats.unresolved_cases'));

        $unresolvedIds = collect($response->json('unresolved_cases.data'))->pluck('id')->all();
        $this->assertCount(2, $unresolvedIds);
        $this->assertContains($tmduCase->id, $unresolvedIds);
        $this->assertContains($tmduAppointment->case_id, $unresolvedIds);
    }

    public function test_sdu_monitoring_only_includes_sdu_appointments_and_cases(): void
    {
        $sdu = $this->staff('sdu_head');
        $sduAppointment = $this->appointment('SDU');
        $this->appointment('GCU');
        $sduCase = $this->caseFile('SDU', 'on_hold', true);
        $this->caseFile('GCU', 'on_hold', true);

        Sanctum::actingAs($sdu);
        $response = $this->getJson('/api/monitoring')->assertOk();

        $this->assertSame(1, $response->json('stats.pending_appointments'));
        $this->assertSame(2, $response->json('stats.unresolved_cases'));

        $unresolvedIds = collect($response->json('unresolved_cases.data'))->pluck('id')->all();
        $this->assertCount(2, $unresolvedIds);
        $this->assertContains($sduCase->id, $unresolvedIds);
        $this->assertContains($sduAppointment->case_id, $unresolvedIds);
    }

    public function test_monitoring_stats_are_not_limited_to_the_first_paginated_page(): void
    {
        $user = $this->staff('gcu_staff');
        for ($i = 0; $i < 12; $i++) {
            $this->appointment('GCU');
        }

        Sanctum::actingAs($user);
        $response = $this->getJson('/api/monitoring')->assertOk();

        $this->assertSame(12, $response->json('stats.pending_appointments'));
        $this->assertSame(10, $response->json('pending_appointments.per_page'));
        $this->assertSame(12, $response->json('pending_appointments.total'));
    }

    public function test_monitoring_excludes_complaint_linked_referrals_from_pending_referral_queue(): void
    {
        $user = $this->staff('gcu_staff');
        $student = Student::factory()->create();
        $case = CaseFile::factory()->create(['student_id' => $student->id, 'current_unit' => 'GCU']);
        $ordinary = Referral::factory()->create([
            'student_id' => $student->id,
            'case_id' => $case->id,
            'status' => 'submitted',
            'referral_type' => 'counseling',
            'complaint_id' => null,
        ]);
        $complaint = Complaint::create([
            'complainee_student_id' => $student->id,
            'violation_type' => 'Test violation',
            'description' => 'Complaint linked to the referral.',
            'filed_by_user_id' => User::factory()->create(['role' => 'sdu_head'])->id,
        ]);
        Referral::factory()->create([
            'student_id' => $student->id,
            'case_id' => $case->id,
            'status' => 'submitted',
            'referral_type' => 'disciplinary',
            'complaint_id' => $complaint->id,
        ]);

        Sanctum::actingAs($user);
        $response = $this->getJson('/api/monitoring')->assertOk();

        $this->assertSame(1, $response->json('stats.pending_referrals'));
        $this->assertSame([$ordinary->id], collect($response->json('pending_referrals.data'))->pluck('id')->all());
    }
}
