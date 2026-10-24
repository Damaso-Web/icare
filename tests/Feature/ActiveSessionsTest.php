<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

// B569: "Active Sessions / Linked Devices" - a user can list every token
// (device) currently authenticated on their account, sign out any one of
// them, or sign out every device but the one they're using right now.
class ActiveSessionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_list_and_revoke_a_session(): void
    {
        $user = User::factory()->create(['role' => 'gcu_staff']);
        $current = $user->createToken('Chrome on Windows');
        $other = $user->createToken('Safari on iPhone');

        Sanctum::actingAs($user, [], 'sanctum');
        // Make the "current" token the one Sanctum treats as this request's token.
        $user->withAccessToken($current->accessToken);

        $list = $this->getJson('/api/me/sessions')->assertOk()->json();
        $this->assertCount(2, $list);

        $this->deleteJson("/api/me/sessions/{$other->accessToken->id}")->assertOk();
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $other->accessToken->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $current->accessToken->id]);
    }

    public function test_staff_can_revoke_all_other_sessions_at_once(): void
    {
        $user = User::factory()->create(['role' => 'gcu_staff']);
        $current = $user->createToken('Chrome on Windows');
        $user->createToken('Safari on iPhone');
        $user->createToken('Firefox on Linux');

        Sanctum::actingAs($user, [], 'sanctum');
        $user->withAccessToken($current->accessToken);

        $this->postJson('/api/me/sessions/revoke-others')->assertOk();
        $this->assertSame(1, $user->tokens()->count());
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $current->accessToken->id]);
    }

    public function test_cannot_revoke_another_accounts_session(): void
    {
        $user = User::factory()->create(['role' => 'gcu_staff']);
        $other = User::factory()->create(['role' => 'gcu_staff']);
        $otherToken = $other->createToken('Chrome on Windows');

        Sanctum::actingAs($user);
        $this->deleteJson("/api/me/sessions/{$otherToken->accessToken->id}")->assertStatus(404);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $otherToken->accessToken->id]);
    }

    public function test_student_can_list_and_revoke_a_session(): void
    {
        $student = Student::factory()->create();
        $current = $student->createToken('Chrome on Windows', ['student']);
        $other = $student->createToken('Safari on iPhone', ['student']);

        Sanctum::actingAs($student, ['student'], 'student');
        $student->withAccessToken($current->accessToken);

        $list = $this->getJson('/api/student/sessions')->assertOk()->json();
        $this->assertCount(2, $list);

        $this->deleteJson("/api/student/sessions/{$other->accessToken->id}")->assertOk();
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $other->accessToken->id]);
    }
}