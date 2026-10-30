<?php

namespace Tests\Feature;

use App\Models\CaseFile;
use App\Models\CaseHandoff;
use App\Models\ParentConferenceSlip;
use App\Models\Referral;
use App\Models\Student;
use App\Models\TestingRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

// CaseController's escalation/handoff/flagging actions - referToTmdu (both
// the brand-new-escalation and re-click-reuses-the-open-one paths),
// referExternal, handoff/acknowledgeHandoff, flagUnreachable, flagFollowUp/
// resolveFollowUp, issueParentConferenceSlip/saveParentConferenceNotes, the
// unit-scoped index for TMDU/SDU, and summary() had no dedicated tests
// before. index/show/update/updateStatus/close are already covered in
// CaseReassignmentTest and FeedbackNotesAttendedTest.
class CaseActionsTest extends TestCase
{
    use RefreshDatabase;

    private function gcuStaff(): User
    {
        return User::factory()->create(['role' => 'gcu_staff', 'is_active' => true]);
    }

    private function caseFile(array $attrs = []): CaseFile
    {
        return CaseFile::factory()->create($attrs);
    }

    // A referral that's already past GCU's own intake step and has an
    // attended appointment - the minimum canEditSif() requires before it's
    // eligible for "Refer to TMDU".
    private function escalatableReferral(CaseFile $case): Referral
    {
        $referral = Referral::factory()->create([
            'student_id' => $case->student_id, 'case_id' => $case->id, 'status' => 'acknowledged',
        ]);
        \App\Models\Appointment::create([
            'appointment_code' => 'APT-' . uniqid(), 'case_id' => $case->id, 'referral_id' => $referral->id,
            'student_id' => $case->student_id, 'created_by_user_id' => $this->gcuStaff()->id,
            'appointment_type' => 'initial_counseling', 'unit' => 'GCU',
            'appointment_date' => now()->toDateString(), 'start_time' => '09:00', 'end_time' => '10:00',
            'status' => 'completed', 'request_status' => 'completed',
        ]);
        return $referral;
    }

    // #referToTmdu: a brand new escalation.
    public function test_refer_to_tmdu_creates_a_referral_and_testing_record(): void
    {
        Notification::fake();
        $tmdu = User::factory()->create(['role' => 'tmdu_staff', 'is_active' => true]);
        $case = $this->caseFile();
        $source = $this->escalatableReferral($case);

        $res = $this->actingAs($this->gcuStaff())
            ->postJson("/api/cases/{$case->id}/refer-tmdu", ['reason' => 'Needs psychological testing.', 'referral_id' => $source->id])
            ->assertOk();

        $this->assertFalse($res->json('referral_was_reused'));
        $this->assertSame('psychological_testing', $res->json('referral.referral_type'));
        $this->assertSame('TMDU', $case->fresh()->current_unit);
        $this->assertSame('awaiting_testing', $case->fresh()->status);
        $this->assertTrue((bool) $case->fresh()->referred_to_tmdu);
        Notification::assertSentTo($tmdu, \App\Notifications\TestingReferralNotification::class);
        Notification::assertSentTo($case->student, \App\Notifications\ReferredToTmduNotification::class);
    }

    public function test_refer_to_tmdu_is_refused_before_the_referral_has_an_attended_appointment(): void
    {
        $case = $this->caseFile();
        $source = Referral::factory()->create(['student_id' => $case->student_id, 'case_id' => $case->id, 'status' => 'acknowledged']);

        $this->actingAs($this->gcuStaff())
            ->postJson("/api/cases/{$case->id}/refer-tmdu", ['reason' => 'Too early.', 'referral_id' => $source->id])
            ->assertStatus(422);
    }

    // Re-clicking "Refer to TMDU" from the SAME referral reuses its still-
    // open escalation instead of forking a duplicate referral/testing record.
    public function test_refer_to_tmdu_reuses_the_same_referrals_open_escalation(): void
    {
        $case = $this->caseFile();
        $source = $this->escalatableReferral($case);
        $this->actingAs($this->gcuStaff())
            ->postJson("/api/cases/{$case->id}/refer-tmdu", ['reason' => 'First reason.', 'referral_id' => $source->id])
            ->assertOk();
        $this->assertSame(1, TestingRecord::count());

        $res = $this->actingAs($this->gcuStaff())
            ->postJson("/api/cases/{$case->id}/refer-tmdu", ['reason' => 'Updated reason.', 'referral_id' => $source->id])
            ->assertOk();

        $this->assertTrue($res->json('referral_was_reused'));
        $this->assertSame(1, TestingRecord::count());
        $this->assertSame(1, Referral::where('referral_type', 'psychological_testing')->count());
    }

    // A DIFFERENT referral on the same case always starts its own,
    // independent escalation rather than being blocked by the first one.
    public function test_refer_to_tmdu_from_a_different_referral_starts_a_separate_escalation(): void
    {
        $case = $this->caseFile();
        $first = $this->escalatableReferral($case);
        $second = $this->escalatableReferral($case);
        $this->actingAs($this->gcuStaff())
            ->postJson("/api/cases/{$case->id}/refer-tmdu", ['reason' => 'First.', 'referral_id' => $first->id])
            ->assertOk();

        $res = $this->actingAs($this->gcuStaff())
            ->postJson("/api/cases/{$case->id}/refer-tmdu", ['reason' => 'Second.', 'referral_id' => $second->id])
            ->assertOk();

        $this->assertFalse($res->json('referral_was_reused'));
        $this->assertSame(2, TestingRecord::count());
    }

    // #referExternal
    public function test_refer_external_sets_destination_and_status(): void
    {
        $case = $this->caseFile();

        $this->actingAs($this->gcuStaff())
            ->postJson("/api/cases/{$case->id}/refer-external", ['destination' => 'Benguet Mental Health Clinic', 'reason' => 'Needs psychiatric care.'])
            ->assertOk();

        $case->refresh();
        $this->assertTrue((bool) $case->referred_externally);
        $this->assertSame('Benguet Mental Health Clinic', $case->external_referral_destination);
        $this->assertSame('awaiting_external', $case->status);
    }

    // #handoff / #acknowledgeHandoff
    public function test_handoff_moves_the_case_and_notifies_the_recipient(): void
    {
        Notification::fake();
        $sdu = User::factory()->create(['role' => 'sdu_head', 'is_active' => true]);
        $case = $this->caseFile(['current_unit' => 'GCU']);

        $this->actingAs($this->gcuStaff())
            ->postJson("/api/cases/{$case->id}/handoff", [
                'to_user_id' => $sdu->id, 'to_unit' => 'SDU', 'reason' => 'Needs SDU involvement.',
            ])->assertOk();

        $this->assertSame('SDU', $case->fresh()->current_unit);
        $this->assertSame(1, CaseHandoff::where('case_id', $case->id)->count());
        Notification::assertSentTo($sdu, \App\Notifications\CaseHandoffNotification::class);
    }

    public function test_acknowledge_handoff_records_who_acknowledged_and_notifies_the_sender(): void
        {
            Notification::fake();
            $sender = $this->gcuStaff();
            $sdu = User::factory()->create(['role' => 'sdu_head', 'is_active' => true]);
            // current_unit must match the handoff's to_unit (the controller's
            // "still the active receiving assignment" check).
            $case = $this->caseFile(['current_unit' => 'SDU']);
            $handoff = CaseHandoff::create([
                'case_id'     => $case->id,
                'from_user_id' => $sender->id,
                'to_user_id'   => $sdu->id,
                'from_unit'   => 'GCU',
                'to_unit'     => 'SDU',
                'reason'      => 'test',
            ]);

            $this->actingAs($sdu)
                ->postJson("/api/cases/{$case->id}/handoffs/{$handoff->id}/acknowledge")
                ->assertOk();

            $this->assertTrue((bool) $handoff->fresh()->acknowledged);
            Notification::assertSentTo($sender, \App\Notifications\HandoffAcknowledgedNotification::class);
        }

    public function test_acknowledge_handoff_rejects_a_handoff_from_a_different_case(): void
    {
        $case = $this->caseFile();
        $otherCase = $this->caseFile();
        $handoff = CaseHandoff::create([
            'case_id' => $otherCase->id, 'from_user_id' => $this->gcuStaff()->id,
            'to_user_id' => $this->gcuStaff()->id, 'from_unit' => 'GCU', 'to_unit' => 'SDU', 'reason' => 'test',
        ]);

        $this->actingAs($this->gcuStaff())
            ->postJson("/api/cases/{$case->id}/handoffs/{$handoff->id}/acknowledge")
            ->assertStatus(422);
    }

    // #flagUnreachable
    public function test_flag_unreachable_notifies_the_students_college_dean(): void
    {
        Notification::fake();
        $dean = User::factory()->create(['role' => 'dean', 'is_active' => true, 'college' => 'College of Informatics and Computing Sciences']);
        $student = Student::factory()->create(['college' => 'College of Informatics and Computing Sciences']);
        $case = $this->caseFile(['student_id' => $student->id]);

        $this->actingAs($this->gcuStaff())
            ->postJson("/api/cases/{$case->id}/flag-unreachable", ['notes' => 'Not answering calls.'])
            ->assertOk();

        $this->assertTrue((bool) $case->fresh()->student_unreachable);
        Notification::assertSentTo($dean, \App\Notifications\UnreachableStudentNotification::class);
    }

    // #flagFollowUp / #resolveFollowUp
    public function test_flag_and_resolve_follow_up(): void
    {
        $case = $this->caseFile();

        $this->actingAs($this->gcuStaff())
            ->postJson("/api/cases/{$case->id}/flag-follow-up", ['notes' => 'Check in again next month.'])
            ->assertOk();
        $this->assertTrue((bool) $case->fresh()->requires_follow_up);

        $this->actingAs($this->gcuStaff())
            ->postJson("/api/cases/{$case->id}/resolve-follow-up")
            ->assertOk();
        $this->assertFalse((bool) $case->fresh()->requires_follow_up);
    }

    // #issueParentConferenceSlip / #saveParentConferenceNotes
    public function test_issue_parent_conference_slip_notifies_the_student(): void
    {
        Notification::fake();
        $case = $this->caseFile();

        $res = $this->actingAs($this->gcuStaff())
            ->postJson("/api/cases/{$case->id}/parent-conference-slip", [
                'conference_date' => now()->addDays(3)->toDateString(),
                'conference_time' => '13:00',
                'reason'          => 'Discuss academic performance',
            ])->assertOk();

        $this->assertSame(1, ParentConferenceSlip::where('case_id', $case->id)->count());
        Notification::assertSentTo($case->student, \App\Notifications\ParentConferenceSlipNotification::class);
        $this->assertNotNull($res->json('id'));
    }

    public function test_parent_conference_notes_can_only_be_saved_once(): void
    {
        $case = $this->caseFile();
        $slip = ParentConferenceSlip::create([
            'case_id' => $case->id, 'issued_by_user_id' => $this->gcuStaff()->id,
            'conference_date' => now()->toDateString(), 'conference_time' => '13:00', 'reason' => 'test',
            'issued_at' => now(),
        ]);

        $this->actingAs($this->gcuStaff())
            ->postJson("/api/parent-conference-slips/{$slip->id}/notes", ['notes' => 'Parent attended, discussed grades.'])
            ->assertOk();
        $this->assertNotNull($slip->fresh()->notes);

        $this->actingAs($this->gcuStaff())
            ->postJson("/api/parent-conference-slips/{$slip->id}/notes", ['notes' => 'Trying to change it.'])
            ->assertStatus(422);
    }

    // #index: TMDU has no Student Information File access at all (it works
    // from the Testing Records module instead) - authorizeStaffAccess()
    // refuses the whole list, not just a unit-scoped view of it.
    public function test_index_is_refused_entirely_for_tmdu_and_sdu_staff(): void
    {
        $tmdu = User::factory()->create(['role' => 'tmdu_staff', 'is_active' => true]);
        $sdu = User::factory()->create(['role' => 'sdu_head', 'is_active' => true]);

        $this->actingAs($tmdu)->getJson('/api/cases')->assertStatus(403);
        $this->actingAs($sdu)->getJson('/api/cases')->assertStatus(403);
    }

    // #index: search matches the case number as well as the student's name,
    // and the archived flag swaps between the working list and the archive.
    public function test_index_search_matches_case_number_or_student_name_and_archived_flag_swaps_lists(): void
    {
        $findable = Student::factory()->create(['first_name' => 'Juanita', 'last_name' => 'Reyes']);
        $openCase = $this->caseFile(['student_id' => $findable->id, 'status' => 'open']);
        Referral::factory()->create(['student_id' => $findable->id, 'case_id' => $openCase->id, 'acknowledged_at' => now()]);

        $other = $this->caseFile(['status' => 'open']);
        Referral::factory()->create(['student_id' => $other->student_id, 'case_id' => $other->id, 'acknowledged_at' => now()]);

        $closedStudent = Student::factory()->create();
        $closedCase = $this->caseFile(['student_id' => $closedStudent->id, 'status' => 'closed']);
        Referral::factory()->create(['student_id' => $closedStudent->id, 'case_id' => $closedCase->id, 'acknowledged_at' => now()]);

        $this->actingAs($this->gcuStaff());

        $byName = $this->getJson('/api/cases?search=Reyes')->assertOk()->json('data');
        $this->assertCount(1, $byName);
        $this->assertSame($openCase->id, $byName[0]['id']);

        $byNumber = $this->getJson('/api/cases?search=' . $openCase->case_number)->assertOk()->json('data');
        $this->assertCount(1, $byNumber);

        $working = $this->getJson('/api/cases')->assertOk()->json('data');
        $workingIds = collect($working)->pluck('id');
        $this->assertFalse($workingIds->contains($closedCase->id));

        $archived = $this->getJson('/api/cases?archived=1')->assertOk()->json('data');
        $archivedIds = collect($archived)->pluck('id');
        $this->assertTrue($archivedIds->contains($closedCase->id));
        $this->assertFalse($archivedIds->contains($openCase->id));
    }

    public function test_tmdu_staff_cannot_view_a_gcu_cases_sif(): void
    {
        $gcuCase = $this->caseFile(['current_unit' => 'GCU']);
        $tmdu = User::factory()->create(['role' => 'tmdu_staff', 'is_active' => true]);

        $this->actingAs($tmdu)->getJson("/api/cases/{$gcuCase->id}")->assertStatus(403);
    }

    // #summary
    public function test_summary_loads_the_full_case_history_and_logs_an_export(): void
    {
        $case = $this->caseFile();
        Referral::factory()->create(['student_id' => $case->student_id, 'case_id' => $case->id]);

        $this->actingAs($this->gcuStaff())->getJson("/api/cases/{$case->id}/summary")->assertOk();
        $this->assertSame(1, \App\Models\AuditLog::where('action', 'exported')->count());
    }
}