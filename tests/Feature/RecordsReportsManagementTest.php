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

class RecordsReportsManagementTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $role, array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role' => $role,
            'is_active' => true,
        ], $attributes));
    }

    private function referral(string $type = 'counseling', array $attributes = []): Referral
    {
        $student = $attributes['student_id'] ?? Student::factory()->create()->id;
        $case = $attributes['case_id'] ?? CaseFile::factory()->create(['student_id' => $student])->id;

        return Referral::factory()->create(array_merge([
            'student_id' => $student,
            'case_id' => $case,
            'referral_type' => $type,
            'status' => 'submitted',
            'created_at' => now(),
        ], $attributes));
    }

    public function test_report_routes_require_an_authenticated_staff_account(): void
    {
        foreach ([
            '/api/reports/referrals',
            '/api/reports/appointments',
            '/api/reports/cases',
            '/api/reports/recurring-concerns',
            '/api/reports/services',
            '/api/reports/dashboard',
        ] as $uri) {
            $this->getJson($uri)->assertUnauthorized();
        }
    }

    public function test_non_reporting_role_cannot_access_report_endpoints(): void
    {
        Sanctum::actingAs($this->staff('faculty'));

        $this->getJson('/api/reports/referrals')->assertForbidden();
        $this->getJson('/api/reports/export/excel')->assertForbidden();
    }

    public function test_report_date_filters_are_validated(): void
    {
        Sanctum::actingAs($this->staff('gcu_staff'));

        $this->getJson('/api/reports/referrals?date_from=not-a-date')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('date_from');

        $this->getJson('/api/reports/cases?date_to=not-a-date')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('date_to');
    }

    public function test_gcu_referral_report_contains_expected_aggregates(): void
    {
        Sanctum::actingAs($this->staff('gcu_staff'));
        $this->referral('counseling');
        $this->referral('follow_up');

        $response = $this->getJson('/api/reports/referrals')->assertOk();

        $response->assertJsonStructure([
            'total',
            'by_status',
            'by_type',
            'by_student_college',
            'by_source',
            'by_college',
            'monthly_trend',
        ]);
        $this->assertSame(2, $response->json('total'));
    }

    public function test_tmdu_referral_report_excludes_non_testing_referrals(): void
    {
        Sanctum::actingAs($this->staff('tmdu_staff'));
        $testing = $this->referral('psychological_testing');
        $this->referral('counseling');

        $response = $this->getJson('/api/reports/referrals')->assertOk();

        $this->assertSame(1, $response->json('total'));
        $this->assertSame('psychological_testing', $response->json('by_type.0.referral_type'));
    }

    public function test_sdu_referral_report_is_restricted_to_sdu_role(): void
    {
        Sanctum::actingAs($this->staff('gcu_staff'));
        $this->getJson('/api/reports/complaints')->assertForbidden();
        $this->getJson('/api/reports/sdu-referrals')->assertForbidden();

        Sanctum::actingAs($this->staff('sdu_head'));
        $this->getJson('/api/reports/complaints')->assertOk();
        $this->getJson('/api/reports/sdu-referrals')->assertOk();
    }

    public function test_gcu_accomplishment_report_is_restricted_to_gcu_unit(): void
    {
        Sanctum::actingAs($this->staff('tmdu_staff'));
        $this->getJson('/api/reports/gcu-accomplishment')->assertForbidden();

        Sanctum::actingAs($this->staff('gcu_staff'));
        $this->getJson('/api/reports/gcu-accomplishment')->assertOk();
    }

    public function test_reports_return_empty_aggregates_when_the_date_window_has_no_records(): void
    {
        Sanctum::actingAs($this->staff('gcu_staff'));

        $response = $this->getJson('/api/reports/referrals?date_from=2000-01-01&date_to=2000-01-31')
            ->assertOk();

        $this->assertSame(0, $response->json('total'));
        $this->assertSame([], $response->json('monthly_trend'));
    }

    public function test_report_export_endpoints_return_downloadable_workbooks(): void
    {
        Sanctum::actingAs($this->staff('gcu_staff'));

        $excel = $this->get('/api/reports/export/excel?report=referrals')->assertOk();
        $this->assertStringContainsString(
            'spreadsheetml',
            (string) $excel->headers->get('Content-Type')
        );

        $pdf = $this->get('/api/reports/export/pdf?report=referrals')->assertOk();
        $this->assertStringContainsString('pdf', (string) $pdf->headers->get('Content-Type'));
    }

    public function test_export_rejects_unknown_report_names(): void
    {
        Sanctum::actingAs($this->staff('gcu_staff'));

        $this->getJson('/api/reports/export/excel?report=not-a-report')->assertStatus(422);
        $this->getJson('/api/reports/export/pdf?report=not-a-report')->assertStatus(422);
    }

    public function test_dashboard_report_exposes_the_documented_summary_fields(): void
    {
        Sanctum::actingAs($this->staff('gcu_staff'));

        $response = $this->getJson('/api/reports/dashboard')->assertOk();

        $response->assertJsonStructure([
            'total_students',
            'total_referrals',
            'total_cases',
            'open_cases',
            'closed_cases',
            'total_appointments',
        ]);
    }

    public function test_case_report_includes_case_summary_fields(): void
    {
        Sanctum::actingAs($this->staff('gcu_staff'));
        $student = Student::factory()->create();
        CaseFile::factory()->create([
            'student_id' => $student->id,
            'current_unit' => 'GCU',
            'status' => 'open',
        ]);

        $response = $this->getJson('/api/reports/cases')->assertOk();
        $this->assertArrayHasKey('total', $response->json());
        $this->assertGreaterThanOrEqual(1, $response->json('total'));
    }
}
