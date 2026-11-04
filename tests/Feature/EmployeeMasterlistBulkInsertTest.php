<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

// B#, 11/4/2026: UserController::importConfirm() now bulk-inserts new rows
// instead of one User::create() per row (the actual cause of a big
// Faculty/Employee masterlist feeling stuck on "Confirm" - see the comment
// above $inserts in importConfirm()). This covers: a normal multi-row
// create still produces correct, independent accounts and temp passwords;
// and two new rows that collide on a unique column (here, Employee ID)
// within the same upload fall back to being inserted one at a time, so only
// the actual colliding row is skipped - not the whole 500-row chunk it
// landed in.
class EmployeeMasterlistBulkInsertTest extends TestCase
{
    use RefreshDatabase;

    private function csv(array $rows): UploadedFile
    {
        $lines = ['Last Name,First Name,Middle Name,Suffix,Email Address,Role,Employee ID,College,Department,Contact Number'];
        foreach ($rows as $r) $lines[] = implode(',', $r);
        return UploadedFile::fake()->createWithContent('staff.csv', implode("\n", $lines));
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_multiple_new_rows_are_bulk_created_correctly(): void
    {
        Sanctum::actingAs($this->admin());
        $file = $this->csv([
            ['Santos', 'Mia', '', '', 'mia.santos@bsu.edu.ph', 'gcu_staff', 'E-900', '', '', ''],
            ['Reyes', 'Ana', '', '', 'ana.reyes@bsu.edu.ph', 'sdu_head', 'E-901', '', '', ''],
            ['Cruz', 'Jun', '', '', 'jun.cruz@bsu.edu.ph', 'gcu_staff', 'E-902', '', '', ''],
        ]);

        $res = $this->postJson('/api/users/import-preview', ['file' => $file])->assertOk();
        $token = $res->json('token');
        $confirm = $this->postJson('/api/users/import-confirm', [
            'token' => $token, 'decisions' => [0 => 'create', 1 => 'create', 2 => 'create'],
        ])->assertOk();

        $this->assertSame(3, $confirm->json('created'));
        $this->assertCount(3, $confirm->json('passwords'));

        $mia = User::where('email', 'mia.santos@bsu.edu.ph')->first();
        $this->assertNotNull($mia);
        $this->assertSame('Mia Santos', $mia->name);
        $this->assertSame('gcu_staff', $mia->role);
        $this->assertTrue($mia->must_change_password);
        $this->assertNotNull(User::where('email', 'ana.reyes@bsu.edu.ph')->first());
        $this->assertNotNull(User::where('email', 'jun.cruz@bsu.edu.ph')->first());
    }

    public function test_a_colliding_employee_id_within_the_same_upload_only_skips_that_row(): void
    {
        Sanctum::actingAs($this->admin());
        $file = $this->csv([
            ['Santos', 'Mia', '', '', 'mia.santos@bsu.edu.ph', 'gcu_staff', 'E-900', '', '', ''],
            // Same Employee ID as the row above - a mistake in the file, not
            // caught by import-preview's own in-file duplicate check if, say,
            // the emails differ (preview's duplicate check is keyed on email
            // first). Confirm must still only drop this one row.
            ['Reyes', 'Ana', '', '', 'ana.reyes@bsu.edu.ph', 'sdu_head', 'E-900', '', '', ''],
            ['Cruz', 'Jun', '', '', 'jun.cruz@bsu.edu.ph', 'gcu_staff', 'E-902', '', '', ''],
        ]);

        $res = $this->postJson('/api/users/import-preview', ['file' => $file])->assertOk();
        $token = $res->json('token');
        $confirm = $this->postJson('/api/users/import-confirm', [
            'token' => $token, 'decisions' => [0 => 'create', 1 => 'create', 2 => 'create'],
        ])->assertOk();

        $this->assertSame(2, $confirm->json('created'));
        $this->assertNotNull(User::where('email', 'mia.santos@bsu.edu.ph')->first());
        $this->assertNull(User::where('email', 'ana.reyes@bsu.edu.ph')->first());
        $this->assertNotNull(User::where('email', 'jun.cruz@bsu.edu.ph')->first());

        $passwordEmails = collect($confirm->json('passwords'))->pluck('email')->all();
        $this->assertNotContains('ana.reyes@bsu.edu.ph', $passwordEmails);
        $this->assertContains('mia.santos@bsu.edu.ph', $passwordEmails);
        $this->assertContains('jun.cruz@bsu.edu.ph', $passwordEmails);
    }
}