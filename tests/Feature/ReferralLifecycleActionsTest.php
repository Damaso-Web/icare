<?php

namespace Tests\Feature;

use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

// Requirements 10-12 (view/edit/archive referral): delete, cancellation
// (void) request/approve/deny, archive/unarchive, correcting or reassigning
// the referred student before acknowledgement, and the tracking view - none
// had dedicated tests before. index/show/store/status/assign/feedback/
// admission-slip are already covered elsewhere.
class ReferralLifecycleActionsTest extends TestCase
{
    use RefreshDatabase;

    private function gcuStaff(): User
    {
        return User::factory()->create(['role' => 'gcu_staff', 'is_active' => true]);
    }

    private function referral(array $attrs = []): Referral
    {
        $referrer = $attrs['referred_by_user_id'] ?? User::factory()->create(['role' => 'faculty', 'is_active' => true])->id;
        return Referral::factory()->create(array_merge(['referred_by_user_id' => $referrer], $attrs));
    }

    // #12-adjacent: delete is only for the referrer's own, still-unacknowledged referral.
    public function test_referrer_can_delete_their_own_unacknowledged_referral(): void
    {
        $referrer = User::factory()->create(['role' => 'faculty', 'is_active' => true]);
        $ref = $this->referral(['referred_by_user_id' => $referrer->id]);

        $this->actingAs($referrer)->deleteJson("/api/referrals/{$ref->id}")->assertOk();
        $this->assertNull(Referral::find($ref->id));
    }

    public function test_delete_is_refused_once_acknowledged_and_for_someone_elses_referral(): void
    {
        $referrer = User::factory()->create(['role' => 'faculty', 'is_active' => true]);
        $other = User::factory()->create(['role' => 'faculty', 'is_active' => true]);
        $acked = $this->referral(['referred_by_user_id' => $referrer->id, 'acknowledged_at' => now()]);
        $unacked = $this->referral(['referred_by_user_id' => $referrer->id]);

        $this->actingAs($referrer)->deleteJson("/api/referrals/{$acked->id}")->assertStatus(422);
        $this->actingAs($other)->deleteJson("/api/referrals/{$unacked->id}")->assertStatus(403);
    }

    // #12: request-void / approve-void / deny-void - the referrer's path once acknowledged.
    public function test_referrer_can_request_a_void_once_acknowledged(): void
    {
        Notification::fake();
        $gcu = $this->gcuStaff();
        $referrer = User::factory()->create(['role' => 'faculty', 'is_active' => true]);
        $ref = $this->referral(['referred_by_user_id' => $referrer->id, 'acknowledged_at' => now()]);

        $this->actingAs($referrer)
            ->postJson("/api/referrals/{$ref->id}/request-void", ['reason' => 'Filed against the wrong student.'])
            ->assertOk();

        $this->assertNotNull($ref->fresh()->void_requested_at);
        Notification::assertSentTo($gcu, \App\Notifications\VoidRequestedNotification::class);
    }

    public function test_request_void_is_refused_before_acknowledgement_or_when_already_pending(): void
    {
        $referrer = User::factory()->create(['role' => 'faculty', 'is_active' => true]);
        $unacked = $this->referral(['referred_by_user_id' => $referrer->id]);
        $pending = $this->referral(['referred_by_user_id' => $referrer->id, 'acknowledged_at' => now(), 'void_requested_at' => now()]);

        $this->actingAs($referrer)
            ->postJson("/api/referrals/{$unacked->id}/request-void", ['reason' => 'Not acknowledged yet, test.'])
            ->assertStatus(422);
        $this->actingAs($referrer)
            ->postJson("/api/referrals/{$pending->id}/request-void", ['reason' => 'Already pending, test.'])
            ->assertStatus(422);
    }

    public function test_gcu_can_approve_a_void_request_and_it_archives_the_referral(): void
    {
        Notification::fake();
        $referrer = User::factory()->create(['role' => 'faculty', 'is_active' => true]);
        $ref = $this->referral([
            'referred_by_user_id' => $referrer->id, 'acknowledged_at' => now(),
            'void_requested_at' => now(), 'void_reason' => 'Wrong student.',
        ]);

        $this->actingAs($this->gcuStaff())->postJson("/api/referrals/{$ref->id}/approve-void")->assertOk();

        $this->assertTrue((bool) $ref->fresh()->is_archived);
        $this->assertNull($ref->fresh()->void_requested_at);
        Notification::assertSentTo($referrer, \App\Notifications\VoidRequestDecidedNotification::class);
    }

    public function test_gcu_can_deny_a_void_request_and_it_stays_active(): void
    {
        Notification::fake();
        $referrer = User::factory()->create(['role' => 'faculty', 'is_active' => true]);
        $ref = $this->referral([
            'referred_by_user_id' => $referrer->id, 'acknowledged_at' => now(),
            'void_requested_at' => now(), 'void_reason' => 'Wrong student.',
        ]);

        $this->actingAs($this->gcuStaff())
            ->postJson("/api/referrals/{$ref->id}/deny-void", ['note' => 'Referral is correct as filed.'])
            ->assertOk();

        $this->assertFalse((bool) $ref->fresh()->is_archived);
        $this->assertNull($ref->fresh()->void_requested_at);
    }

    public function test_approve_and_deny_void_require_a_pending_request(): void
    {
        $ref = $this->referral(['acknowledged_at' => now()]);
        $this->actingAs($this->gcuStaff());

        $this->postJson("/api/referrals/{$ref->id}/approve-void")->assertStatus(422);
        $this->postJson("/api/referrals/{$ref->id}/deny-void")->assertStatus(422);
    }

    // #12: archive / unarchive (direct GCU action, no void request needed).
    public function test_archive_and_unarchive_a_referral(): void
    {
        $ref = $this->referral();
        $this->actingAs($this->gcuStaff());

        $this->postJson("/api/referrals/{$ref->id}/archive")->assertOk();
        $this->assertTrue((bool) $ref->fresh()->is_archived);

        $this->postJson("/api/referrals/{$ref->id}/unarchive")->assertOk();
        $this->assertFalse((bool) $ref->fresh()->is_archived);
    }

    public function test_tmdu_cannot_archive_a_gcu_referral_and_vice_versa(): void
    {
        $gcuRef = $this->referral(['referral_type' => 'counseling']);
        $tmdu = User::factory()->create(['role' => 'tmdu_staff', 'is_active' => true]);

        $this->actingAs($tmdu)->postJson("/api/referrals/{$gcuRef->id}/archive")
            ->assertStatus(403)
            ->assertJson(['message' => 'TMDU can only modify its own Case Referral Slips.']);
    }

    // #11: referrer correcting the linked student's own details pre-acknowledgement.
    public function test_referrer_can_correct_the_referred_students_details_before_acknowledgement(): void
    {
        $referrer = User::factory()->create(['role' => 'faculty', 'is_active' => true]);
        $student = Student::factory()->create(['first_name' => 'Typo']);
        $ref = $this->referral(['referred_by_user_id' => $referrer->id, 'student_id' => $student->id]);

        $this->actingAs($referrer)
            ->putJson("/api/referrals/{$ref->id}/student", ['first_name' => 'Fixed'])
            ->assertOk()
            ->assertJsonPath('first_name', 'Fixed');

        $this->assertSame('Fixed', $student->fresh()->first_name);
    }

    public function test_correcting_the_referred_student_is_refused_once_acknowledged(): void
    {
        $referrer = User::factory()->create(['role' => 'faculty', 'is_active' => true]);
        $ref = $this->referral(['referred_by_user_id' => $referrer->id, 'acknowledged_at' => now()]);

        $this->actingAs($referrer)
            ->putJson("/api/referrals/{$ref->id}/student", ['first_name' => 'Changed'])
            ->assertStatus(422);
    }

    // #11: reassigning the referral to a different, already-existing student entirely.
    public function test_referrer_can_reassign_the_referral_to_a_different_existing_student(): void
    {
        $referrer = User::factory()->create([
            'role' => 'faculty', 'is_active' => true,
            'college' => 'College of Informatics and Computing Sciences', 'department' => 'BS Information Technology',
        ]);
        $wrongStudent = Student::factory()->create();
        $rightStudent = Student::factory()->create();
        $ref = $this->referral(['referred_by_user_id' => $referrer->id, 'student_id' => $wrongStudent->id]);

        $res = $this->actingAs($referrer)
            ->putJson("/api/referrals/{$ref->id}/reassign-student", ['student_id' => $rightStudent->id])
            ->assertOk();

        $this->assertSame($rightStudent->id, $res->json('student_id'));
        $this->assertSame($rightStudent->id, $ref->fresh()->student_id);
    }

    public function test_reassign_student_rejects_a_deactivated_student(): void
    {
        $referrer = User::factory()->create(['role' => 'faculty', 'is_active' => true]);
        $inactive = Student::factory()->create(['is_active' => false]);
        $ref = $this->referral(['referred_by_user_id' => $referrer->id]);

        $this->actingAs($referrer)
            ->putJson("/api/referrals/{$ref->id}/reassign-student", ['student_id' => $inactive->id])
            ->assertStatus(422);
    }

    public function test_reassign_student_is_refused_once_acknowledged_or_for_someone_elses_referral(): void
    {
        $referrer = User::factory()->create(['role' => 'faculty', 'is_active' => true]);
        $other = User::factory()->create(['role' => 'faculty', 'is_active' => true]);
        $newStudent = Student::factory()->create();
        $acked = $this->referral(['referred_by_user_id' => $referrer->id, 'acknowledged_at' => now()]);
        $unacked = $this->referral(['referred_by_user_id' => $referrer->id]);

        $this->actingAs($referrer)
            ->putJson("/api/referrals/{$acked->id}/reassign-student", ['student_id' => $newStudent->id])
            ->assertStatus(422);
        $this->actingAs($other)
            ->putJson("/api/referrals/{$unacked->id}/reassign-student", ['student_id' => $newStudent->id])
            ->assertStatus(403);
    }

    // #10: archived-list view, and the tracking summary.
    public function test_archived_list_only_shows_archived_referrals(): void
    {
        $this->referral(['is_archived' => false]);
        $archived = $this->referral(['is_archived' => true]);
        $this->actingAs($this->gcuStaff());

        $res = $this->getJson('/api/referrals-archived')->assertOk();
        $this->assertCount(1, $res->json('data'));
        $this->assertSame($archived->id, $res->json('data.0.id'));
    }

    public function test_archived_list_scopes_faculty_to_their_own_referrals(): void
    {
        $faculty = User::factory()->create(['role' => 'faculty', 'is_active' => true]);
        $other = User::factory()->create(['role' => 'faculty', 'is_active' => true]);
        $this->referral(['referred_by_user_id' => $faculty->id, 'is_archived' => true]);
        $this->referral(['referred_by_user_id' => $other->id, 'is_archived' => true]);

        $res = $this->actingAs($faculty)->getJson('/api/referrals-archived')->assertOk();
        $this->assertCount(1, $res->json('data'));
    }

    public function test_tracking_returns_referral_case_and_appointment_summary(): void
    {
        $case = CaseFile::factory()->create(['total_sessions' => 2]);
        $ref = $this->referral(['case_id' => $case->id, 'student_id' => $case->student_id]);
        $this->actingAs($this->gcuStaff());

        $res = $this->getJson("/api/referrals/{$ref->id}/tracking")->assertOk();
        $this->assertSame($ref->referral_code, $res->json('referral.referral_code'));
        $this->assertSame($case->case_number, $res->json('case.case_number'));
    }
}