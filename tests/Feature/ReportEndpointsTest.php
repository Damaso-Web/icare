<?php

namespace Tests\Feature;

use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

// B645 (10/30/2026): the project's test suite runs against an in-memory
// SQLite database (phpunit.xml), but several report queries used
// MySQL-only SQL (MONTH()/YEAR()/DATEDIFF()) or a HAVING clause on a
// non-aggregate withCount() column, which MySQL tolerates but SQLite
// rejects outright. Every one of these endpoints used to 500 under the
// test suite even though they work fine against the real MySQL database -
// these are permanent regressions for that, covering both the grouping/
// math itself and that the export downloads succeed end to end.
class ReportEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private function gcuStaff(): User
    {
        return User::factory()->create(['role' => 'gcu_staff']);
    }

    private function referral(Student $student, string $createdAt, string $type = 'counseling'): Referral
    {
        $case = CaseFile::where('student_id', $student->id)->first() ?: CaseFile::factory()->create(['student_id' => $student->id]);
        $r = Referral::create([
            'case_id' => $case->id, 'student_id' => $student->id, 'referral_type' => $type,
            'nature_of_concern' => 'Needs follow-up and support.', 'status' => 'submitted',
            'referrer_name' => 'Test Referrer', 'referrer_role' => 'faculty', 'referrer_source' => 'faculty',
        ]);
        $r->forceFill(['created_at' => $createdAt])->save();
        return $r;
    }

    public function test_referral_report_groups_data_by_source_and_month(): void
    {
        Sanctum::actingAs($this->gcuStaff());
        $s1 = Student::factory()->create();
        $s2 = Student::factory()->create();
        $this->referral($s1, '2026-01-15 10:00:00');
        $this->referral($s2, '2026-01-20 10:00:00');
        $this->referral($s1, '2026-02-05 10:00:00');

        $res = $this->getJson('/api/reports/referrals')->assertOk();
        $trend = collect($res->json('monthly_trend'));

        $jan = $trend->firstWhere('month', 1);
        $feb = $trend->firstWhere('month', 2);
        $this->assertNotNull($jan);
        $this->assertNotNull($feb);
        $this->assertSame(2026, (int) $jan['year']);
        $this->assertSame(2, (int) $jan['count']);
        $this->assertSame(1, (int) $feb['count']);
    }

    public function test_recurring_concerns_report_counts_students_with_more_than_one_referral(): void
    {
        Sanctum::actingAs($this->gcuStaff());
        $recurring = Student::factory()->create();
        $once = Student::factory()->create();
        $this->referral($recurring, now()->toDateTimeString());
        $this->referral($recurring, now()->toDateTimeString());
        $this->referral($once, now()->toDateTimeString());

        $res = $this->getJson('/api/reports/recurring-concerns')->assertOk();

        $this->assertSame(1, $res->json('total_recurring_students'));
        $ids = collect($res->json('top_recurring_students'))->pluck('id')->all();
        $this->assertContains($recurring->id, $ids);
        $this->assertNotContains($once->id, $ids);
    }

    public function test_staff_can_download_an_excel_appointments_report(): void
    {
        Sanctum::actingAs($this->gcuStaff());
        $student = Student::factory()->create();
        $this->referral($student, now()->toDateTimeString());

        $res = $this->get('/api/reports/export/excel?report=appointments');
        $res->assertOk();
        $this->assertSame('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $res->headers->get('Content-Type'));
    }

    public function test_gcu_can_download_the_complete_accomplishment_workbook(): void
    {
        Sanctum::actingAs($this->gcuStaff());
        $student = Student::factory()->create();
        $this->referral($student, now()->toDateTimeString());

        $res = $this->get('/api/reports/export/excel');
        $res->assertOk();
        $this->assertSame('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $res->headers->get('Content-Type'));
    }

    public function test_tmdu_can_download_a_case_report_workbook(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'tmdu_staff']));
        $student = Student::factory()->create();
        CaseFile::factory()->create(['student_id' => $student->id, 'current_unit' => 'TMDU']);

        $res = $this->get('/api/reports/export/excel?report=cases');
        $res->assertOk();
        $this->assertSame('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $res->headers->get('Content-Type'));
    }

    public function test_case_report_average_days_to_close_is_computed_portably(): void
    {
        Sanctum::actingAs($this->gcuStaff());
        $student = Student::factory()->create();
        $case = CaseFile::factory()->create([
            'student_id' => $student->id, 'status' => 'closed',
            'opened_date' => now()->subDays(10)->toDateString(),
            'closed_date' => now()->toDateString(),
        ]);

        $res = $this->getJson('/api/reports/cases')->assertOk();
        $this->assertEqualsWithDelta(10.0, $res->json('avg_days_to_close'), 0.5);
    }
}