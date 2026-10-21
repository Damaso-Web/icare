<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\Referral;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SentReferralsComplaintsTest extends TestCase
{
    use RefreshDatabase;

    private function student(string $id, string $first): Student
    {
        return Student::create([
            'student_id' => $id, 'first_name' => $first, 'last_name' => 'Cruz', 'year_level' => '1st Year',
            'college' => 'CIT', 'program' => 'BSIT', 'password' => Hash::make('x'), 'is_active' => true,
        ]);
    }

    public function test_every_staff_role_can_refer_and_sees_only_their_own_sent_list(): void
    {
        Notification::fake();
        $target = $this->student('2300001', 'Target');
        foreach (['faculty', 'dean', 'dept_chair', 'gcu_staff', 'sdu_head', 'tmdu_staff', 'tmdu_head', 'admin', 'system_admin'] as $role) {
            $u = User::factory()->create(['role' => $role, 'college' => 'CIT', 'is_active' => true]);
            Sanctum::actingAs($u);
            $this->postJson('/api/referrals', [
                'student_id' => $target->id, 'referral_type' => 'counseling', 'nature_of_concern' => 'Needs someone to talk to.',
            ])->assertCreated();
            $this->getJson('/api/my/referrals')->assertOk()->assertJsonCount(1, 'data');
        }
    }

    public function test_student_can_refer_another_student_and_see_it_in_sent_list(): void
    {
        Notification::fake();
        $me = $this->student('2300002', 'Me');
        $friend = $this->student('2300003', 'Friend');
        Sanctum::actingAs($me, [], 'student');

        $this->postJson('/api/student/outreach/lookup', ['student_id' => '2300003'])->assertOk()->assertJsonPath('id', $friend->id);
        $this->postJson('/api/student/outreach/lookup', ['student_id' => '2300002'])->assertStatus(404);
        $this->postJson('/api/student/outreach/lookup', ['student_id' => '999'])->assertStatus(404);

        $this->postJson('/api/student/outreach/referrals', [
            'student_id' => $friend->id, 'referral_type' => 'counseling', 'nature_of_concern' => 'Seems very stressed lately.',
        ])->assertCreated();
        $this->postJson('/api/student/outreach/referrals', [
            'student_id' => $me->id, 'referral_type' => 'counseling', 'nature_of_concern' => 'Seems very stressed lately.',
        ])->assertStatus(422);
        $this->postJson('/api/student/outreach/referrals', [
            'student_id' => $friend->id, 'referral_type' => 'disciplinary', 'nature_of_concern' => 'Seems very stressed lately.',
        ])->assertStatus(422);

        $this->getJson('/api/student/outreach/referrals')->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame($me->id, Referral::first()->referred_by_student_id);
    }

    public function test_student_can_file_complaint_and_only_sees_their_own(): void
    {
        Notification::fake();
        $me = $this->student('2300004', 'Me');
        $other = $this->student('2300005', 'Other');
        Sanctum::actingAs($me, [], 'student');

        $r = $this->postJson('/api/student/outreach/complaints', [
            'complainant_address' => 'Batangas City', 'complainee_student_id' => $other->id,
            'violation_type' => 'Bullying', 'incident_date' => now()->subDay()->toDateString(),
            'description' => 'It happened in the hallway.', 'certification_agreed' => true,
        ]);
        $r->assertStatus(201)->assertJsonPath("filer_name", 'Me Cruz');
        $id = $r->json('id');
        $this->assertNull(Complaint::find($id)->filed_by_user_id);
        $this->assertSame(1, Referral::where('complaint_id', $id)->count());

        $this->getJson('/api/student/outreach/complaints')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/student/outreach/complaints/{$id}")->assertOk();

        Sanctum::actingAs($other, [], 'student');
        $this->getJson("/api/student/outreach/complaints/{$id}")->assertStatus(403);
        $this->getJson('/api/student/outreach/complaints')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_staff_sent_complaints_list_and_privacy(): void
    {
        Notification::fake();
        $a = User::factory()->create(['role' => 'faculty', 'college' => 'CIT', 'is_active' => true]);
        $b = User::factory()->create(['role' => 'faculty', 'college' => 'CIT', 'is_active' => true]);
        $s = $this->student('2300006', 'Subj');
        Sanctum::actingAs($a);
        \Illuminate\Support\Facades\Storage::fake('public');
        $data = [
            'complainant_name' => 'Faculty A', 'complainant_address' => 'Batangas', 'complainee_student_id' => $s->id,
            'violation_type' => 'Bullying', 'incident_date' => now()->subDay()->toDateString(),
            'description' => 'Seen in class.', 'certification_agreed' => true,
        ];
        $json = ['Accept' => 'application/json'];

        // Evidence and the witness affidavit are both required for staff.
        $this->withHeaders($json)->post('/api/complaints', $data)->assertStatus(422)->assertJsonValidationErrors(['evidence', 'affidavit']);
        $this->withHeaders($json)->post('/api/complaints', $data + ['evidence' => [\Illuminate\Http\UploadedFile::fake()->create('e.pdf', 10)]])
            ->assertStatus(422)->assertJsonValidationErrors(['affidavit']);

        $id = $this->withHeaders($json)->post('/api/complaints', $data + [
            'evidence'  => [\Illuminate\Http\UploadedFile::fake()->create('e.pdf', 10)],
            'affidavit' => [\Illuminate\Http\UploadedFile::fake()->create('a.pdf', 10)],
        ])->assertStatus(201)->json("id");

        $this->getJson('/api/my/complaints')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/my/complaints/{$id}")->assertOk();
        Sanctum::actingAs($b);
        $this->getJson('/api/my/complaints')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/my/complaints/{$id}")->assertStatus(403);
    }

    public function test_referrer_is_told_when_referral_is_reassigned(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $staff = User::factory()->create(['role' => 'gcu_staff', 'is_active' => true]);
        $faculty = User::factory()->create(['role' => 'faculty', 'college' => 'CIT', 'is_active' => true]);
        $s = $this->student('2300007', 'Ref');
        Sanctum::actingAs($faculty);
        $id = $this->postJson('/api/referrals', [
            'student_id' => $s->id, 'referral_type' => 'counseling', 'nature_of_concern' => 'Needs someone to talk to.',
        ])->assertCreated()->json('id');

        Sanctum::actingAs($admin);
        $this->postJson("/api/referrals/{$id}/assign", ['user_id' => $staff->id])->assertOk();
        Notification::assertSentTo($faculty, \App\Notifications\CaseUpdateNotification::class);
    }

    public function test_sender_can_edit_until_acknowledged(): void
    {
        Notification::fake();
        $staff = User::factory()->create(['role' => 'sdu_head', 'is_active' => true]);
        $other = User::factory()->create(['role' => 'faculty', 'college' => 'CIT', 'is_active' => true]);
        $s = $this->student('2300008', 'Edit');
        Sanctum::actingAs($staff);
        $id = $this->postJson('/api/referrals', [
            'student_id' => $s->id, 'referral_type' => 'counseling', 'nature_of_concern' => 'Original concern text.',
        ])->assertCreated()->json('id');

        $this->putJson("/api/my/referrals/{$id}", ['nature_of_concern' => 'Corrected concern text.', 'referral_type' => 'shifting'])->assertOk();
        $this->assertSame('Corrected concern text.', Referral::find($id)->nature_of_concern);
        $this->putJson("/api/my/referrals/{$id}", ['referral_type' => 'disciplinary'])->assertStatus(422);

        Sanctum::actingAs($other);
        $this->putJson("/api/my/referrals/{$id}", ['nature_of_concern' => 'Not my referral.'])->assertStatus(404);

        Referral::whereKey($id)->update(['status' => 'acknowledged', 'acknowledged_at' => now()]);
        Sanctum::actingAs($staff);
        $this->putJson("/api/my/referrals/{$id}", ['nature_of_concern' => 'Too late to change.'])->assertStatus(422);
    }

    public function test_student_sender_can_edit_until_acknowledged(): void
    {
        Notification::fake();
        $me = $this->student('2300009', 'Me');
        $friend = $this->student('2300010', 'Friend');
        Sanctum::actingAs($me, [], 'student');
        $id = $this->postJson('/api/student/outreach/referrals', [
            'student_id' => $friend->id, 'referral_type' => 'counseling', 'nature_of_concern' => 'Original concern text.',
        ])->assertCreated()->json('id');
        $this->putJson("/api/student/outreach/referrals/{$id}", ['nature_of_concern' => 'Corrected concern text.'])->assertOk();
        Referral::whereKey($id)->update(['acknowledged_at' => now()]);
        $this->putJson("/api/student/outreach/referrals/{$id}", ['nature_of_concern' => 'Too late to change.'])->assertStatus(422);
    }

    public function test_oss_heads_and_staff_can_edit_their_own_unacknowledged_referral_via_update(): void
    {
        Notification::fake();
        $s = $this->student('2300011', 'Oss');
        foreach (['admin', 'gcu_staff', 'sdu_head', 'tmdu_head', 'tmdu_staff'] as $role) {
            $u = User::factory()->create(['role' => $role, 'is_active' => true]);
            Sanctum::actingAs($u);
            $id = $this->postJson('/api/referrals', [
                'student_id' => $s->id, 'referral_type' => 'counseling', 'nature_of_concern' => 'Original concern text.',
            ])->assertCreated()->json('id');
            $this->getJson("/api/referrals/{$id}")->assertOk();
            $this->putJson("/api/referrals/{$id}", ['nature_of_concern' => "Corrected by {$role}."])->assertOk();
            $this->assertSame("Corrected by {$role}.", Referral::find($id)->nature_of_concern);
            Referral::whereKey($id)->update(['acknowledged_at' => now()]);
            if ($role !== 'admin' && $role !== 'gcu_staff') {
                $this->putJson("/api/referrals/{$id}", ['nature_of_concern' => 'Too late now.'])->assertStatus(422);
            }
        }
    }
}