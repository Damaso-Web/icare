<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TmduHeadUploadTest extends TestCase
{
    use RefreshDatabase;

    private function csv(array $rows): UploadedFile
    {
        $lines = ['Last Name,First Name,Middle Name,Suffix,Email Address,Role,Employee ID,College,Department,Contact Number'];
        foreach ($rows as $r) $lines[] = implode(',', $r);
        return UploadedFile::fake()->createWithContent('staff.csv', implode("\n", $lines));
    }

    public function test_tmdu_head_can_preview_and_confirm_a_tmdu_staff_upload(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'tmdu_head', 'unit' => 'TMDU']));
        $file = $this->csv([['Reyes', 'Ana', '', '', 'ana.reyes@bsu.edu.ph', 'tmdu_staff', 'T-100', '', '', '']]);

        $res = $this->postJson('/api/users/import-preview', ['file' => $file])->assertOk();
        $this->assertSame(1, $res->json('total'));
        $token = $res->json('token');
        $this->assertNotEmpty($token);

        $this->postJson('/api/users/import-confirm', ['token' => $token, 'decisions' => [0 => 'create']])->assertOk();
        $this->assertSame('tmdu_staff', User::where('email', 'ana.reyes@bsu.edu.ph')->value('role'));
    }

    public function test_tmdu_head_cannot_upload_other_roles_or_touch_other_accounts(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'tmdu_head', 'unit' => 'TMDU']));
        User::factory()->create(['role' => 'gcu_staff', 'email' => 'gcu@bsu.edu.ph']);
        $file = $this->csv([
            ['Cruz', 'Jun', '', '', 'jun.cruz@bsu.edu.ph', 'admin', 'T-101', '', '', ''],
            ['Lim', 'Bea', '', '', 'gcu@bsu.edu.ph', 'tmdu_staff', 'T-102', '', '', ''],
        ]);

        $res = $this->postJson('/api/users/import-preview', ['file' => $file])->assertOk();
        $token = $res->json('token');
        $this->postJson('/api/users/import-confirm', ['token' => $token, 'decisions' => [0 => 'create', 1 => 'update']])->assertOk();

        $this->assertNull(User::where('email', 'jun.cruz@bsu.edu.ph')->first());
        $this->assertSame('gcu_staff', User::where('email', 'gcu@bsu.edu.ph')->value('role'));
    }

    public function test_other_staff_roles_still_cannot_upload(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'gcu_staff', 'unit' => 'GCU']));
        $this->postJson('/api/users/import-preview', ['file' => $this->csv([])])->assertStatus(403);
    }

    public function test_registration_not_found_message_points_to_the_college(): void
    {
        $this->postJson('/api/register', ['employee_id' => 'NOPE-1', 'email' => 'x@bsu.edu.ph', 'consent_accepted' => true])
            ->assertStatus(422)
            ->assertJsonPath('errors.employee_id.0', fn ($m) => str_starts_with($m, 'Employee not found.') && str_contains($m, 'College'));
    }
}