<?php

namespace Tests\Feature;

use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\Student;
use App\Models\StudentStatusEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Requirements 3 (edit student profile), 4 (deactivate student profile),
// 41/42/45 (view case / multiple referrals / combined history), and 64
// (view client profile) - StudentController::update(), destroy(), graduate(),
// history(), statusHistory(), remindForms(), checkDuplicateName(), and
// updateClientNo() had no dedicated tests before. index()/show()/store()/
// toggle-active/reset-password/temp-password are already covered elsewhere
// (AccessControlRegressionTest, TempPasswordHashingTest, PasswordResetAdminTest).
class StudentProfileManagementTest extends TestCase
{
    use RefreshDatabase;

    private function gcuStaff(): User
    {
        return User::factory()->create(['role' => 'gcu_staff', 'is_active' => true]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    // #3: edit student profile.
    public function test_gcu_staff_can_edit_a_student_profile(): void
    {
        $student = Student::factory()->create(['first_name' => 'Old', 'year_level' => '1st Year']);
        $this->actingAs($this->gcuStaff());

        $res = $this->putJson("/api/students/{$student->id}", [
            'first_name' => 'New', 'last_name' => $student->last_name, 'year_level' => '2nd Year',
        ])->assertOk();

        $this->assertSame('New', $res->json('first_name'));
        $this->assertSame('2nd Year', $student->fresh()->year_level);
    }

    public function test_non_admin_cannot_change_student_id_email_or_sex(): void
    {
        $student = Student::factory()->create(['sex' => 'Male', 'email' => 'original@bsu.edu.ph']);
        $this->actingAs($this->gcuStaff());

        $this->putJson("/api/students/{$student->id}", ['sex' => 'Female'])->assertStatus(403);
        $this->putJson("/api/students/{$student->id}", ['email' => 'changed@bsu.edu.ph'])->assertStatus(403);
        $this->assertSame('Male', $student->fresh()->sex);
    }

    public function test_admin_can_change_student_id_email_and_sex(): void
    {
        $student = Student::factory()->create(['sex' => 'Male']);
        $this->actingAs($this->admin());

        $this->putJson("/api/students/{$student->id}", ['sex' => 'Female'])->assertOk();
        $this->assertSame('Female', $student->fresh()->sex);
    }

    public function test_edit_rejects_an_email_already_used_by_another_account(): void
    {
        Student::factory()->create(['email' => 'taken@bsu.edu.ph']);
        $student = Student::factory()->create(['email' => 'mine@bsu.edu.ph']);
        $this->actingAs($this->admin());

        $this->putJson("/api/students/{$student->id}", ['email' => 'taken@bsu.edu.ph'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    // B647 (10/30/2026): the global ConvertEmptyStringsToNull middleware
    // converts a blank field to null before validate() runs, so the
    // controller's "leave unchanged when blank" logic has to check for
    // null, not just '' - checking only '' never matched a real request.
    public function test_edit_treats_a_blank_optional_field_as_leave_unchanged(): void
    {
        $student = Student::factory()->create(['section' => 'B', 'college' => 'College of Business']);
        $this->actingAs($this->gcuStaff());

        $this->putJson("/api/students/{$student->id}", ['section' => '', 'college' => ''])->assertOk();

        $student->refresh();
        $this->assertSame('B', $student->section);
        $this->assertSame('College of Business', $student->college);
    }

    // #4: deactivate student profile (graduate() despite its name handles
    // every deactivation reason, not only "graduated").
    public function test_admin_can_deactivate_a_student_and_it_closes_open_cases(): void
    {
        $student = Student::factory()->create();
        $case = CaseFile::factory()->create(['student_id' => $student->id, 'status' => 'open']);
        $this->actingAs($this->admin());

        $res = $this->postJson("/api/students/{$student->id}/graduate", [
            'deactivation_reason' => 'withdrawn_dropped',
        ])->assertOk();

        $this->assertFalse((bool) $res->json('is_active'));
        $this->assertSame('closed', $case->fresh()->status);
        $this->assertSame(1, StudentStatusEvent::where('student_id', $student->id)->where('action', 'deactivated')->count());
    }

    public function test_deactivation_requires_a_note_when_reason_is_other(): void
    {
        $student = Student::factory()->create();
        $this->actingAs($this->admin());

        $this->postJson("/api/students/{$student->id}/graduate", ['deactivation_reason' => 'other'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['deactivation_notes']);
    }

    public function test_deactivation_archives_active_referrals(): void
    {
        $student = Student::factory()->create();
        $case = CaseFile::factory()->create(['student_id' => $student->id]);
        $referral = Referral::factory()->create(['student_id' => $student->id, 'case_id' => $case->id, 'status' => 'submitted']);
        $this->actingAs($this->admin());

        $this->postJson("/api/students/{$student->id}/graduate", ['deactivation_reason' => 'graduated'])->assertOk();

        $this->assertTrue((bool) $referral->fresh()->is_archived);
    }

    public function test_only_admin_can_deactivate_or_reactivate(): void
    {
        $student = Student::factory()->create(['is_active' => false]);
        $this->actingAs($this->gcuStaff());

        $this->postJson("/api/students/{$student->id}/graduate", ['deactivation_reason' => 'graduated'])->assertStatus(403);
        $this->postJson("/api/students/{$student->id}/toggle-active", ['reason' => 'error'])->assertStatus(403);
    }

    public function test_status_history_lists_deactivation_and_reactivation_events_newest_first(): void
    {
        $student = Student::factory()->create();
        $this->actingAs($this->admin());

        $this->postJson("/api/students/{$student->id}/graduate", ['deactivation_reason' => 'graduated'])->assertOk();
        $this->postJson("/api/students/{$student->id}/toggle-active", ['reason' => 're_enrolled'])->assertOk();

        $res = $this->getJson("/api/students/{$student->id}/status-history")->assertOk();
        $this->assertCount(2, $res->json());
        $this->assertSame('reactivated', $res->json('0.action'));
        $this->assertSame('deactivated', $res->json('1.action'));
    }

    // Hard delete (destroy) - distinct from deactivate.
    public function test_admin_can_permanently_delete_a_student(): void
    {
        $student = Student::factory()->create();
        $this->actingAs($this->admin());

        $this->deleteJson("/api/students/{$student->id}")->assertOk();
        $this->assertNull(Student::find($student->id));
    }

    public function test_remind_forms_fails_for_a_deactivated_student(): void
    {
        $student = Student::factory()->create(['is_active' => false]);
        $this->actingAs($this->gcuStaff());

        $this->postJson("/api/students/{$student->id}/remind-forms")
            ->assertStatus(422)
            ->assertJson(['message' => 'This student account is deactivated, so it cannot receive the reminder.']);
    }

    public function test_remind_forms_succeeds_for_an_active_student(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        $student = Student::factory()->create(['is_active' => true]);
        $this->actingAs($this->gcuStaff());

        $this->postJson("/api/students/{$student->id}/remind-forms")->assertOk();
        \Illuminate\Support\Facades\Notification::assertSentTo($student, \App\Notifications\FillOutFormsNotification::class);
    }

    // #13/#14-adjacent: used by Create Referral to warn about a likely
    // duplicate student before creating a new record.
    public function test_check_duplicate_name_reports_an_existing_match(): void
    {
        Student::factory()->create(['first_name' => 'Juan', 'last_name' => 'Dela Cruz']);
        $this->actingAs($this->gcuStaff());

        $res = $this->postJson('/api/students/check-duplicate-name', ['first_name' => 'Juan', 'last_name' => 'Dela Cruz'])->assertOk();
        $this->assertTrue($res->json('duplicate_found'));
    }

    public function test_check_duplicate_name_reports_no_match_for_a_new_name(): void
    {
        $this->actingAs($this->gcuStaff());

        $res = $this->postJson('/api/students/check-duplicate-name', ['first_name' => 'Nobody', 'last_name' => 'Matching'])->assertOk();
        $this->assertFalse($res->json('duplicate_found'));
    }

    // SIF client no.
    public function test_update_client_no_sets_and_rejects_duplicates(): void
    {
        Student::factory()->create(['sif_client_no' => 100]);
        $student = Student::factory()->create();
        $this->actingAs($this->gcuStaff());

        $this->patchJson("/api/students/{$student->id}/client-no", ['sif_client_no' => 100])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['sif_client_no']);

        $this->patchJson("/api/students/{$student->id}/client-no", ['sif_client_no' => 200])
            ->assertOk()
            ->assertJson(['sif_client_no' => 200]);
    }

    public function test_update_client_no_is_blocked_on_an_archived_deactivated_student(): void
    {
        $student = Student::factory()->create(['is_active' => false]);
        $this->actingAs($this->gcuStaff());

        $this->patchJson("/api/students/{$student->id}/client-no", ['sif_client_no' => 300])
            ->assertStatus(422)
            ->assertJson(['message' => 'This file is archived and view only. The Control Number can no longer be changed.']);
    }

    // #41/#42/#45: combined case/referral/appointment/testing history,
    // scoped per role.
    public function test_history_gives_gcu_staff_the_students_full_record(): void
    {
        $student = Student::factory()->create();
        $case = CaseFile::factory()->create(['student_id' => $student->id]);
        Referral::factory()->create(['student_id' => $student->id, 'case_id' => $case->id]);
        $this->actingAs($this->gcuStaff());

        $res = $this->getJson("/api/students/{$student->id}/history")->assertOk();
        $this->assertCount(1, $res->json('cases'));
        $this->assertCount(1, $res->json('referrals'));
    }

    public function test_history_scopes_faculty_to_only_their_own_referrals(): void
{
    $student = Student::factory()->create(['college' => 'CIT', 'program' => 'BSIT']);
    $case = CaseFile::factory()->create(['student_id' => $student->id]);
    $faculty = User::factory()->create([
        'role' => 'faculty', 'is_active' => true,
        'college' => 'CIT', 'department' => 'BSIT',
    ]);
    $other = User::factory()->create([
        'role' => 'faculty', 'is_active' => true,
        'college' => 'CIT', 'department' => 'BSIT',
    ]);
    Referral::factory()->create(['student_id' => $student->id, 'case_id' => $case->id, 'referred_by_user_id' => $faculty->id]);
    Referral::factory()->create(['student_id' => $student->id, 'case_id' => $case->id, 'referred_by_user_id' => $other->id]);

    $res = $this->actingAs($faculty)->getJson("/api/students/{$student->id}/history")->assertOk();

    $this->assertCount(1, $res->json('referrals'));
    $this->assertCount(0, $res->json('cases')); // faculty never sees GCU cases
}

    public function test_history_blocks_tmdu_staff_from_a_student_never_referred_to_tmdu(): void
    {
        $student = Student::factory()->create();
        $tmdu = User::factory()->create(['role' => 'tmdu_staff', 'is_active' => true]);

        $this->actingAs($tmdu)->getJson("/api/students/{$student->id}/history")->assertStatus(403);
    }

    public function test_history_allows_tmdu_staff_for_a_student_with_a_testing_record(): void
    {
        $student = Student::factory()->create();
        $referrer = User::factory()->create(['role' => 'faculty']);
        $case = CaseFile::factory()->create(['student_id' => $student->id]);
        \App\Models\TestingRecord::create([
            'case_id' => $case->id, 'student_id' => $student->id, 'referred_by_user_id' => $referrer->id,
        ]);
        $tmdu = User::factory()->create(['role' => 'tmdu_staff', 'is_active' => true]);

        $res = $this->actingAs($tmdu)->getJson("/api/students/{$student->id}/history")->assertOk();
        $this->assertCount(1, $res->json('testing_records'));
        $this->assertCount(0, $res->json('cases')); // TMDU never sees GCU's SIF/cases
    }
}