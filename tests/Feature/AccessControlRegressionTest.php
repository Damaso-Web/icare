<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\CaseFile;
use App\Models\Document;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

// Permanent regression tests for the three confirmed local access-control
// probes recorded in docs/security-testing/ACCESS_CONTROL_STEP_BY_STEP_AND_RESULTS.md
// (10/30/2026 handoff). Each probe was removed after recording its output;
// these recreate it as a maintained test so the denial can't silently regress.
class AccessControlRegressionTest extends TestCase
{
    use RefreshDatabase;

    // -----------------------------------------------------------------
    // 1) A user outside a case's unit could view/download its document (200, expected 403).
    //    Fixed in DocumentController::authorizeAccess() (commit 1d80490).
    // -----------------------------------------------------------------

    private function nonConfidentialDocOnTmduCase(): Document
    {
        $case = CaseFile::factory()->create(['current_unit' => 'TMDU']);
        return Document::create([
            'uploaded_by_user_id' => User::factory()->create(['role' => 'tmdu_staff'])->id,
            'documentable_type'   => CaseFile::class,
            'documentable_id'     => $case->id,
            'original_filename'   => 'notes.pdf',
            'stored_filename'     => 'notes.pdf',
            'disk'                => 'local',
            'path'                => 'docs/notes.pdf',
            'mime_type'           => 'application/pdf',
            'file_size'           => 100,
            'document_type'       => 'supporting_document',
            'is_confidential'     => false,
        ]);
    }

    public function test_sdu_head_cannot_view_a_document_on_a_tmdu_case(): void
    {
        $doc = $this->nonConfidentialDocOnTmduCase();
        Sanctum::actingAs(User::factory()->create(['role' => 'sdu_head']));

        $this->getJson("/api/documents/{$doc->id}")->assertStatus(403);
    }

    public function test_sdu_head_cannot_download_a_document_on_a_tmdu_case(): void
    {
        $doc = $this->nonConfidentialDocOnTmduCase();
        Sanctum::actingAs(User::factory()->create(['role' => 'sdu_head']));

        $this->getJson("/api/documents/{$doc->id}/download")->assertStatus(403);
    }

    public function test_tmdu_staff_can_view_the_document_on_their_own_case(): void
    {
        $doc = $this->nonConfidentialDocOnTmduCase();
        Sanctum::actingAs(User::factory()->create(['role' => 'tmdu_staff']));

        $this->getJson("/api/documents/{$doc->id}")->assertOk();
    }

    public function test_sdu_head_can_view_a_document_on_their_own_unit_case(): void
    {
        $case = CaseFile::factory()->create(['current_unit' => 'SDU']);
        $doc = Document::create([
            'uploaded_by_user_id' => User::factory()->create(['role' => 'sdu_head'])->id,
            'documentable_type'   => CaseFile::class,
            'documentable_id'     => $case->id,
            'original_filename'   => 'notes.pdf',
            'stored_filename'     => 'notes.pdf',
            'disk'                => 'local',
            'path'                => 'docs/notes.pdf',
            'mime_type'           => 'application/pdf',
            'file_size'           => 100,
            'document_type'       => 'supporting_document',
            'is_confidential'     => false,
        ]);
        Sanctum::actingAs(User::factory()->create(['role' => 'sdu_head']));

        $this->getJson("/api/documents/{$doc->id}")->assertOk();
    }

    // -----------------------------------------------------------------
    // 2) An expired scheduling token returned 200 from check-availability /
    //    month-availability instead of 404. Fixed in PublicSchedulingController (B642).
    // -----------------------------------------------------------------

    private function expiredTokenAppointment(): Appointment
    {
        $student = Student::factory()->create();
        return Appointment::create([
            'student_id' => $student->id,
            'staff_user_id' => User::factory()->create(['role' => 'gcu_staff'])->id,
            'unit' => 'GCU', 'appointment_type' => 'initial_counseling',
            'appointment_date' => now()->addWeekday()->toDateString(),
            'start_time' => '09:00', 'end_time' => '10:00', 'duration_minutes' => 60,
            'status' => 'pending', 'request_status' => 'awaiting_student',
            'scheduling_token' => 'expiredtok123',
            'token_expires_at' => now()->subDay(),
        ]);
    }

    public function test_expired_token_check_availability_returns_404(): void
    {
        $this->expiredTokenAppointment();

        $this->postJson('/api/schedule/expiredtok123/check-availability', [
            'appointment_date' => now()->addWeekdays(2)->toDateString(),
            'start_time' => '09:00', 'end_time' => '10:00',
        ])->assertStatus(404);
    }

    public function test_expired_token_month_availability_returns_404(): void
    {
        $this->expiredTokenAppointment();

        $month = now()->addWeekdays(2)->format('Y-m');
        $this->getJson("/api/schedule/expiredtok123/month-availability?month={$month}")->assertStatus(404);
    }

    public function test_valid_unexpired_token_still_works_for_both_endpoints(): void
    {
        $student = Student::factory()->create();
        Appointment::create([
            'student_id' => $student->id,
            'staff_user_id' => User::factory()->create(['role' => 'gcu_staff'])->id,
            'unit' => 'GCU', 'appointment_type' => 'initial_counseling',
            'appointment_date' => now()->addWeekday()->toDateString(),
            'start_time' => '09:00', 'end_time' => '10:00', 'duration_minutes' => 60,
            'status' => 'pending', 'request_status' => 'awaiting_student',
            'scheduling_token' => 'validtok123',
            'token_expires_at' => now()->addDays(7),
        ]);
        $date = now()->addWeekdays(2)->toDateString();

        $this->postJson('/api/schedule/validtok123/check-availability', [
            'appointment_date' => $date, 'start_time' => '09:00', 'end_time' => '10:00',
        ])->assertOk();
        $this->getJson('/api/schedule/validtok123/month-availability?month=' . substr($date, 0, 7))->assertOk();
    }

    // -----------------------------------------------------------------
    // 3) StudentController::show had no college/department scope - a Dean,
    //    Dept Chair, or Faculty account could open any student's full
    //    profile directly by ID. Fixed in StudentController (B641).
    // -----------------------------------------------------------------

    private function student(string $id, string $college, string $program): Student
    {
        return Student::create([
            'student_id' => $id, 'first_name' => 'Juan', 'last_name' => 'Cruz', 'year_level' => '1st Year',
            'college' => $college, 'program' => $program, 'password' => bcrypt('x'), 'is_active' => true,
        ]);
    }

    public function test_faculty_cannot_open_a_student_profile_outside_their_department(): void
    {
        $faculty = User::factory()->create(['role' => 'faculty', 'college' => 'College of Information Sciences', 'department' => 'BS Information Technology']);
        $other = $this->student('2900001', 'College of Information Sciences', 'BS Computer Science');
        Sanctum::actingAs($faculty);

        $this->getJson("/api/students/{$other->id}")->assertStatus(403);
    }

    public function test_dean_cannot_open_a_student_profile_outside_their_college(): void
    {
        $dean = User::factory()->create(['role' => 'dean', 'college' => 'College of Information Sciences']);
        $other = $this->student('2900002', 'College of Business', 'BS Accountancy');
        Sanctum::actingAs($dean);

        $this->getJson("/api/students/{$other->id}")->assertStatus(403);
    }

    public function test_faculty_can_open_a_student_profile_in_their_own_department(): void
    {
        $faculty = User::factory()->create(['role' => 'faculty', 'college' => 'College of Information Sciences', 'department' => 'BS Information Technology']);
        $mine = $this->student('2900003', 'College of Information Sciences', 'BS Information Technology');
        Sanctum::actingAs($faculty);

        $this->getJson("/api/students/{$mine->id}")->assertOk();
    }

    public function test_admin_is_unaffected_by_the_college_scope(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = $this->student('2900004', 'College of Business', 'BS Accountancy');
        Sanctum::actingAs($admin);

        $this->getJson("/api/students/{$student->id}")->assertOk();
    }
}