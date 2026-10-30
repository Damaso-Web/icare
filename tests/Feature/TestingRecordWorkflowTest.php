<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\CaseFile;
use App\Models\Student;
use App\Models\TestingRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

// TMDU's psychological-testing workflow (TestingRecordController): tester
// assignment (incl. B559 reassignment visibility), the ensureTesterAssigned
// gate, index scoping (staff vs. head), update/status locking once results
// are issued, the student OR-photo upload step, schedule-testing, administer
// -tests, schedule-par (incl. its attended-appointment and date gates),
// par-action (released/on-hold/cancel), and send-to-gcu (incl. its
// released-PAR gate). None of these had dedicated tests before.
class TestingRecordWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function tmduStaff(array $attrs = []): User
    {
        return User::factory()->create(array_merge(['role' => 'tmdu_staff', 'is_active' => true], $attrs));
    }

    private function tmduHead(): User
    {
        return User::factory()->create(['role' => 'tmdu_head', 'is_active' => true]);
    }

    private function testingRecord(array $attrs = []): TestingRecord
    {
        $student = $attrs['student_id'] ?? Student::factory()->create()->id;
        $caseId = $attrs['case_id'] ?? (CaseFile::where('student_id', $student)->first()?->id
            ?? CaseFile::factory()->create(['student_id' => $student])->id);
        $referrer = $attrs['referred_by_user_id'] ?? User::factory()->create(['role' => 'faculty'])->id;

        return TestingRecord::create(array_merge([
            'case_id'             => $caseId,
            'student_id'          => $student,
            'referred_by_user_id' => $referrer,
            'status'              => 'pending',
        ], $attrs));
    }

    // Lets the Cloudinary-upload paths (requestTestingByStudent, sendToGcu)
    // run against a faked HTTP response instead of a real network call or
    // the client's own "CLOUDINARY_URL not set" RuntimeException.
    private function fakeCloudinary(): void
    {
        putenv('CLOUDINARY_URL=cloudinary://test_key:test_secret@test_cloud');
        $_ENV['CLOUDINARY_URL'] = 'cloudinary://test_key:test_secret@test_cloud';
        Http::fake([
            'api.cloudinary.com/*' => Http::response([
                'public_id' => 'uploads/' . \Illuminate\Support\Str::uuid(),
                'resource_type' => 'image', 'format' => 'jpg', 'bytes' => 1024,
            ], 200),
        ]);
    }

    // #availableTesters: roster for the "Assign Tester" control.
    public function test_available_testers_lists_only_active_tmdu_staff(): void
    {
        $actor = $this->tmduHead();
        $this->tmduStaff();
        $this->tmduHead();
        User::factory()->create(['role' => 'tmdu_staff', 'is_active' => false]);
        User::factory()->create(['role' => 'gcu_staff', 'is_active' => true]);
        $this->actingAs($actor);

        $res = $this->getJson('/api/testing-records/available-testers')->assertOk();
        $this->assertCount(3, $res->json());
    }

    // #assign: claims the record and notifies the new tester.
    public function test_assign_claims_a_tester_and_notifies_them(): void
    {
        Notification::fake();
        $tester = $this->tmduStaff();
        $record = $this->testingRecord();
        $this->actingAs($this->tmduHead());

        $this->postJson("/api/testing-records/{$record->id}/assign", ['tester_user_id' => $tester->id])
            ->assertOk();

        $this->assertSame($tester->id, $record->fresh()->assigned_tester_user_id);
        Notification::assertSentTo($tester, \App\Notifications\TesterAssignedNotification::class);
    }

    public function test_assign_rejects_a_non_tmdu_user(): void
    {
        $nonTmdu = User::factory()->create(['role' => 'gcu_staff', 'is_active' => true]);
        $record = $this->testingRecord();
        $this->actingAs($this->tmduHead());

        $this->postJson("/api/testing-records/{$record->id}/assign", ['tester_user_id' => $nonTmdu->id])
            ->assertStatus(422);
    }

    // ensureTesterAssigned(): every staff action is blocked until claimed.
    public function test_actions_are_blocked_until_a_tester_is_assigned(): void
    {
        $record = $this->testingRecord();
        $this->actingAs($this->tmduHead());

        $this->postJson("/api/testing-records/{$record->id}/acknowledge")
            ->assertStatus(422)
            ->assertJson(['message' => 'Assign a tester to this record before making any changes.']);
    }

    // B559: once reassigned away, the previous tester can still view the
    // record (index) but can no longer act on it.
    public function test_reassigning_keeps_the_previous_tester_visible_but_blocks_their_actions(): void
    {
        $first = $this->tmduStaff();
        $second = $this->tmduStaff();
        $record = $this->testingRecord(['assigned_tester_user_id' => $first->id]);
        $this->actingAs($this->tmduHead())
            ->postJson("/api/testing-records/{$record->id}/assign", ['tester_user_id' => $second->id])
            ->assertOk();

        $this->assertSame($first->id, $record->fresh()->previous_tester_user_id);

        $res = $this->actingAs($first)->getJson('/api/testing-records')->assertOk();
        $this->assertCount(1, $res->json('data'));

        $this->actingAs($first)
            ->postJson("/api/testing-records/{$record->id}/acknowledge")
            ->assertStatus(403);
    }

    // #acknowledge
    public function test_acknowledge_sets_status_pending_and_notifies_student(): void
    {
        Notification::fake();
        $tester = $this->tmduStaff();
        $record = $this->testingRecord(['assigned_tester_user_id' => $tester->id, 'status' => 'fee_form_pending']);

        $this->actingAs($tester)->postJson("/api/testing-records/{$record->id}/acknowledge")->assertOk();

        $this->assertSame('pending', $record->fresh()->status);
        Notification::assertSentTo($record->student, \App\Notifications\TestingAppointmentReadyNotification::class);
    }

    // #index: tmdu_staff sees own assigned + previously-assigned +
    // unclaimed-but-acknowledged; tmdu_head sees everything.
    public function test_index_scopes_tmdu_staff_but_shows_tmdu_head_everything(): void
    {
        $me = $this->tmduStaff();
        $other = $this->tmduStaff();
        $mine = $this->testingRecord(['assigned_tester_user_id' => $me->id]);
        $notMine = $this->testingRecord(['assigned_tester_user_id' => $other->id]);
        $unclaimedAcked = $this->testingRecord([
            'referral_id' => \App\Models\Referral::factory()->create(['acknowledged_at' => now()])->id,
        ]);
        $unclaimedUnacked = $this->testingRecord();

        $res = $this->actingAs($me)->getJson('/api/testing-records')->assertOk();
        $ids = collect($res->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($mine->id));
        $this->assertTrue($ids->contains($unclaimedAcked->id));
        $this->assertFalse($ids->contains($notMine->id));
        $this->assertFalse($ids->contains($unclaimedUnacked->id));

        $headRes = $this->actingAs($this->tmduHead())->getJson('/api/testing-records')->assertOk();
        $this->assertCount(4, $headRes->json('data'));
    }

    // #show
    public function test_show_loads_the_source_referral_and_logs_the_view(): void
    {
        $record = $this->testingRecord();
        $this->actingAs($this->tmduHead());

        $res = $this->getJson("/api/testing-records/{$record->id}")->assertOk();
        $this->assertArrayHasKey('source_referral', $res->json());
        $this->assertSame(1, \App\Models\AuditLog::where('action', 'viewed')->count());
    }

    // #update: locked once results have already been issued to GCU.
    public function test_update_is_locked_once_results_issued(): void
    {
        $tester = $this->tmduStaff();
        $record = $this->testingRecord(['assigned_tester_user_id' => $tester->id, 'status' => 'test_results_issued']);

        $this->actingAs($tester)
            ->putJson("/api/testing-records/{$record->id}", ['assessment_summary' => 'Changed after the fact.'])
            ->assertStatus(422);
    }

    // #updateStatus: only the early, precondition-free stages are settable here.
    public function test_update_status_only_allows_early_stage_values(): void
    {
        $tester = $this->tmduStaff();
        $record = $this->testingRecord(['assigned_tester_user_id' => $tester->id]);

        $this->actingAs($tester)
            ->patchJson("/api/testing-records/{$record->id}/status", ['status' => 'test_administered'])
            ->assertOk();
        $this->assertSame('test_administered', $record->fresh()->status);

        $this->actingAs($tester)
            ->patchJson("/api/testing-records/{$record->id}/status", ['status' => 'awaiting_results'])
            ->assertStatus(422);
    }

    // #requestTestingByStudent: student uploads OR photo, record moves to
    // or_submitted, and the assigned tester (or every TMDU staff if none
    // assigned yet) is notified.
    public function test_request_testing_by_student_uploads_or_photo_and_notifies_the_tester(): void
    {
        Notification::fake();
        $this->fakeCloudinary();
        $tester = $this->tmduStaff();
        $student = Student::factory()->create();
        $record = $this->testingRecord(['student_id' => $student->id, 'assigned_tester_user_id' => $tester->id]);
        \Laravel\Sanctum\Sanctum::actingAs($student, [], 'student');

        $this->postJson("/api/student/testing-records/{$record->id}/request-testing", [
            'or_photo' => \Illuminate\Http\UploadedFile::fake()->image('or.jpg'),
        ])->assertOk();

        $this->assertSame('or_submitted', $record->fresh()->status);
        $this->assertSame('cloudinary', $record->fresh()->or_photo_disk);
        Notification::assertSentTo($tester, \App\Notifications\TestingRequestSubmittedNotification::class);
    }

    public function test_request_testing_by_student_is_refused_for_someone_elses_record(): void
    {
        $this->fakeCloudinary();
        $record = $this->testingRecord();
        $someoneElse = Student::factory()->create();
        \Laravel\Sanctum\Sanctum::actingAs($someoneElse, [], 'student');

        $this->postJson("/api/student/testing-records/{$record->id}/request-testing", [
            'or_photo' => \Illuminate\Http\UploadedFile::fake()->image('or.jpg'),
        ])->assertStatus(403);
    }

    // #scheduleTesting: creates the test-taking appointment and moves status.
    public function test_schedule_testing_creates_an_appointment_and_moves_status(): void
    {
        Notification::fake();
        $tester = $this->tmduStaff();
        $record = $this->testingRecord(['assigned_tester_user_id' => $tester->id]);
        $date = \Carbon\Carbon::parse('next monday')->toDateString();

        $res = $this->actingAs($tester)
            ->postJson("/api/testing-records/{$record->id}/schedule-testing", [
                'appointment_date' => $date, 'start_time' => '09:00', 'end_time' => '10:00',
            ])->assertOk();

        $this->assertSame('scheduled', $record->fresh()->status);
        $this->assertSame('psychological_testing', $res->json('appointment.appointment_type'));
        Notification::assertSentTo($record->student, \App\Notifications\TestingScheduledNotification::class);
    }

    public function test_schedule_testing_is_blocked_once_tests_have_been_administered(): void
    {
        $tester = $this->tmduStaff();
        $record = $this->testingRecord(['assigned_tester_user_id' => $tester->id, 'status' => 'test_administered']);

        $this->actingAs($tester)
            ->postJson("/api/testing-records/{$record->id}/schedule-testing", [
                'appointment_date' => \Carbon\Carbon::parse('next monday')->toDateString(),
                'start_time' => '09:00', 'end_time' => '10:00',
            ])->assertStatus(422);
    }

    // #administerTests
    public function test_administer_tests_records_tests_and_moves_status(): void
    {
        $tester = $this->tmduStaff();
        $record = $this->testingRecord([
            'assigned_tester_user_id' => $tester->id, 'status' => 'scheduled',
            'testing_date' => \Carbon\Carbon::parse('next monday'),
        ]);

        $this->actingAs($tester)
            ->postJson("/api/testing-records/{$record->id}/administer-tests", [
                'tests_administered' => ['WISC-V', 'Bender-Gestalt'],
            ])->assertOk();

        $record->refresh();
        $this->assertSame('test_administered', $record->status);
        $this->assertSame(['WISC-V', 'Bender-Gestalt'], $record->tests_administered);
    }

    // #schedulePar: requires the student to have actually attended the test-taking appointment.
    public function test_schedule_par_requires_the_student_to_have_attended_testing(): void
    {
        $tester = $this->tmduStaff();
        $record = $this->testingRecord(['assigned_tester_user_id' => $tester->id, 'status' => 'test_administered']);

        $this->actingAs($tester)
            ->postJson("/api/testing-records/{$record->id}/schedule-par", [
                'appointment_date' => \Carbon\Carbon::parse('next monday')->toDateString(),
                'start_time' => '09:00', 'end_time' => '10:00',
            ])->assertStatus(422);
    }

    public function test_schedule_par_rejects_a_date_before_the_recorded_test_date(): void
    {
        $tester = $this->tmduStaff();
        $testDate = \Carbon\Carbon::parse('next monday');
        $record = $this->testingRecord([
            'assigned_tester_user_id' => $tester->id, 'status' => 'test_administered', 'testing_date' => $testDate,
        ]);
        Appointment::create([
            'appointment_code' => 'APT-' . uniqid(), 'case_id' => $record->case_id, 'referral_id' => $record->referral_id,
            'student_id' => $record->student_id, 'staff_user_id' => $tester->id, 'created_by_user_id' => $tester->id,
            'appointment_type' => 'psychological_testing', 'unit' => 'TMDU',
            'appointment_date' => $testDate->toDateString(), 'start_time' => '09:00', 'end_time' => '10:00',
            'status' => 'completed', 'request_status' => 'completed', 'checked_in' => true,
        ]);

        $this->actingAs($tester)
            ->postJson("/api/testing-records/{$record->id}/schedule-par", [
                'appointment_date' => $testDate->copy()->subDay()->toDateString(),
                'start_time' => '09:00', 'end_time' => '10:00',
            ])->assertStatus(422);
    }

    public function test_schedule_par_succeeds_once_the_test_was_attended(): void
    {
        Notification::fake();
        $tester = $this->tmduStaff();
        $testDate = \Carbon\Carbon::parse('next monday');
        $record = $this->testingRecord([
            'assigned_tester_user_id' => $tester->id, 'status' => 'test_administered', 'testing_date' => $testDate,
        ]);
        Appointment::create([
            'appointment_code' => 'APT-' . uniqid(), 'case_id' => $record->case_id, 'referral_id' => $record->referral_id,
            'student_id' => $record->student_id, 'staff_user_id' => $tester->id, 'created_by_user_id' => $tester->id,
            'appointment_type' => 'psychological_testing', 'unit' => 'TMDU',
            'appointment_date' => $testDate->toDateString(), 'start_time' => '09:00', 'end_time' => '10:00',
            'status' => 'completed', 'request_status' => 'completed', 'checked_in' => true,
        ]);

        $this->actingAs($tester)
            ->postJson("/api/testing-records/{$record->id}/schedule-par", [
                'appointment_date' => $testDate->copy()->addDays(7)->toDateString(),
                'start_time' => '13:00', 'end_time' => '14:00',
            ])->assertOk();

        $this->assertSame('awaiting_results', $record->fresh()->status);
    }

    // #parAction: released / on_hold / cancel on the PAR-release appointment.
    private function parReleaseAppointment(TestingRecord $record, User $tester): Appointment
    {
        return Appointment::create([
            'appointment_code' => 'APT-' . uniqid(), 'case_id' => $record->case_id, 'referral_id' => $record->referral_id,
            'student_id' => $record->student_id, 'staff_user_id' => $tester->id, 'created_by_user_id' => $tester->id,
            'appointment_type' => 'par_release', 'unit' => 'TMDU',
            'appointment_date' => \Carbon\Carbon::parse('next monday')->toDateString(),
            'start_time' => '09:00', 'end_time' => '10:00', 'status' => 'pending', 'request_status' => 'confirmed',
        ]);
    }

    public function test_par_action_released_marks_the_appointment_completed(): void
    {
        $tester = $this->tmduStaff();
        $record = $this->testingRecord(['assigned_tester_user_id' => $tester->id]);
        $appt = $this->parReleaseAppointment($record, $tester);

        $this->actingAs($tester)
            ->postJson("/api/appointments/{$appt->id}/par-action", ['action' => 'released'])
            ->assertOk();

        $this->assertSame('completed', $appt->fresh()->status);
    }

    public function test_par_action_on_hold_notifies_the_student(): void
    {
        Notification::fake();
        $tester = $this->tmduStaff();
        $record = $this->testingRecord(['assigned_tester_user_id' => $tester->id]);
        $appt = $this->parReleaseAppointment($record, $tester);

        $this->actingAs($tester)
            ->postJson("/api/appointments/{$appt->id}/par-action", ['action' => 'on_hold'])
            ->assertOk();

        $this->assertNotNull($appt->fresh()->on_hold_at);
        Notification::assertSentTo($appt->student, \App\Notifications\ParOnHoldNotification::class);
    }

    public function test_par_action_cancel_with_a_new_date_books_the_re_release(): void
    {
        Notification::fake();
        $tester = $this->tmduStaff();
        $record = $this->testingRecord(['assigned_tester_user_id' => $tester->id]);
        $appt = $this->parReleaseAppointment($record, $tester);

        $res = $this->actingAs($tester)
            ->postJson("/api/appointments/{$appt->id}/par-action", [
                'action' => 'cancel',
                'appointment_date' => \Carbon\Carbon::parse('next monday')->addDays(7)->toDateString(),
                'start_time' => '09:00', 'end_time' => '10:00',
            ])->assertOk();

        $this->assertSame('cancelled', $appt->fresh()->status);
        $this->assertSame(
            1,
            Appointment::where('rescheduled_from_id', $appt->id)->where('appointment_type', 'par_release')->count()
        );
        $this->assertSame($appt->id, $res->json('appointment.id'));
    }

    // #sendToGcu
    public function test_send_to_gcu_requires_par_to_have_been_released_first(): void
    {
        $tester = $this->tmduStaff();
        $record = $this->testingRecord(['assigned_tester_user_id' => $tester->id, 'status' => 'awaiting_results']);

        $this->actingAs($tester)
            ->postJson("/api/testing-records/{$record->id}/send-to-gcu", [
                'assessment_summary' => 'Summary.', 'recommendations' => 'Recommendations.',
            ])->assertStatus(422);
    }

    public function test_send_to_gcu_attaches_the_report_and_notifies_referrer_and_student(): void
    {
        Notification::fake();
        $this->fakeCloudinary();
        $tester = $this->tmduStaff();
        $referrer = User::factory()->create(['role' => 'faculty']);
        $record = $this->testingRecord([
            'assigned_tester_user_id' => $tester->id, 'status' => 'awaiting_results', 'referred_by_user_id' => $referrer->id,
        ]);
        $this->parReleaseAppointment($record, $tester)->update(['status' => 'completed']);

        $this->actingAs($tester)
            ->postJson("/api/testing-records/{$record->id}/send-to-gcu", [
                'assessment_summary' => 'Summary.', 'recommendations' => 'Recommendations.',
                'report_file' => \Illuminate\Http\UploadedFile::fake()->create('par.pdf', 100, 'application/pdf'),
            ])->assertOk();

        $record->refresh();
        $this->assertSame('test_results_issued', $record->status);
        $this->assertTrue((bool) $record->report_sent_to_gcu);
        $this->assertSame(1, $record->documents()->where('document_type', 'psychological_assessment_report')->count());
        Notification::assertSentTo($referrer, \App\Notifications\ParReadyNotification::class);
        Notification::assertSentTo($record->student, \App\Notifications\ParResultNotification::class);
    }

    public function test_send_to_gcu_is_refused_once_already_issued(): void
    {
        $tester = $this->tmduStaff();
        $record = $this->testingRecord(['assigned_tester_user_id' => $tester->id, 'status' => 'test_results_issued']);
        \App\Models\Document::create([
            'documentable_type' => TestingRecord::class, 'documentable_id' => $record->id,
            'document_type' => 'psychological_assessment_report', 'disk' => 'cloudinary',
            'stored_filename' => 'x', 'original_filename' => 'x.pdf', 'mime_type' => 'application/pdf',
            'file_size' => 1, 'is_confidential' => true, 'uploaded_by_user_id' => $tester->id,
        ]);

        $this->actingAs($tester)
            ->postJson("/api/testing-records/{$record->id}/send-to-gcu", [
                'assessment_summary' => 'Resubmit attempt.', 'recommendations' => 'n/a',
            ])->assertStatus(422);
    }
}