<?php

namespace Tests\Feature;

use App\Models\Referral;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

// B589, B591, B597: Dean-specific account tools - masterlist upload scoped
// to the Dean's own college, reactivation reasons restricted to the
// original deactivation reason, and Sent Referrals filtering by a status
// group (what the dashboard's metric cards now link to).
class DeanAccountToolsTest extends TestCase
{
    use RefreshDatabase;

    private function csv(array $rows): UploadedFile
    {
        $lines = ['Last Name,First Name,Middle Name,Suffix,Email Address,Role,Employee ID,College,Department,Contact Number'];
        foreach ($rows as $r) $lines[] = implode(',', $r);
        return UploadedFile::fake()->createWithContent('staff.csv', implode("\n", $lines));
    }

    public function test_dean_masterlist_upload_is_scoped_to_their_own_college(): void
    {
        $dean = User::factory()->create(['role' => 'dean', 'college' => 'College of Information Sciences']);
        Sanctum::actingAs($dean);

        $file = $this->csv([
            // the row's own College column is ignored - forced to the Dean's college
            ['Santos', 'Mia', '', '', 'mia.santos@bsu.edu.ph', 'faculty', 'F-200', 'College of Business', '', ''],
            // a role outside faculty/dept_chair is rejected for a Dean's upload
            ['Cruz', 'Jun', '', '', 'jun.cruz@bsu.edu.ph', 'admin', 'F-201', '', '', ''],
        ]);

        $res = $this->postJson('/api/users/import-preview', ['file' => $file])->assertOk();
        $token = $res->json('token');
        $this->postJson('/api/users/import-confirm', ['token' => $token, 'decisions' => [0 => 'create', 1 => 'create']])->assertOk();

        $this->assertSame('College of Information Sciences', User::where('email', 'mia.santos@bsu.edu.ph')->value('college'));
        $this->assertNull(User::where('email', 'jun.cruz@bsu.edu.ph')->first());
    }

    public function test_other_roles_still_cannot_use_dean_masterlist_endpoint(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'gcu_staff']));
        $this->postJson('/api/users/import-preview', ['file' => $this->csv([])])->assertStatus(403);
    }

    public function test_reactivation_reason_must_match_the_original_deactivation_reason(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);
        $employee = User::factory()->create(['role' => 'gcu_staff', 'is_active' => true]);

        $this->postJson("/api/users/{$employee->id}/toggle-active", ['reason' => 'end_of_contract'])->assertOk();
        $employee->refresh();
        $this->assertSame('End of Contract / Term', $employee->deactivation_reason);

        // "Security Verified" has nothing to do with "End of Contract / Term".
        $this->postJson("/api/users/{$employee->id}/toggle-active", ['reason' => 'security_verified'])
            ->assertStatus(422)->assertJsonValidationErrors('reason');

        // The matching reason, and the always-available escape hatches, are accepted.
        $this->postJson("/api/users/{$employee->id}/toggle-active", ['reason' => 'rehired'])->assertOk();
        $employee->refresh();
        $this->assertTrue($employee->is_active);
    }

    public function test_reactivation_error_and_other_escape_hatches_always_allowed(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);
        $employee = User::factory()->create(['role' => 'gcu_staff', 'is_active' => true]);

        $this->postJson("/api/users/{$employee->id}/toggle-active", ['reason' => 'security'])->assertOk();
        $this->postJson("/api/users/{$employee->id}/toggle-active", ['reason' => 'error'])->assertOk();
        $this->assertTrue($employee->refresh()->is_active);
    }

    public function test_sent_referrals_accepts_a_comma_separated_status_group(): void
    {
        Notification::fake();
        $dean = User::factory()->create(['role' => 'dean', 'college' => 'College of Information Sciences']);
        Sanctum::actingAs($dean);
        $student = Student::create([
            'student_id' => '2500001', 'first_name' => 'A', 'last_name' => 'B', 'year_level' => '1st Year',
            'college' => 'College of Information Sciences', 'program' => 'BSIT',
            'password' => bcrypt('x'), 'is_active' => true,
        ]);

        Referral::factory()->create(['student_id' => $student->id, 'referred_by_user_id' => $dean->id, 'status' => 'acknowledged']);
        Referral::factory()->create(['student_id' => $student->id, 'referred_by_user_id' => $dean->id, 'status' => 'completed']);
        Referral::factory()->create(['student_id' => $student->id, 'referred_by_user_id' => $dean->id, 'status' => 'submitted']);

        $res = $this->getJson('/api/my/referrals?status=completed,closed')->assertOk();
        $this->assertCount(1, $res->json('data'));
        $this->assertSame('completed', $res->json('data.0.status'));
    }
}