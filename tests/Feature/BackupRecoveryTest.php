<?php

namespace Tests\Feature;

use App\Models\BackupRecord;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

// Requirements 82-85 (Backup & Recovery Management). This module had zero
// dedicated test coverage before - the "verified" lifecycle suites the
// 2026-10-30 handoff cites (BackupManagementTest.php, CronAndBackupTest.php)
// don't exist in the repo, same as the ReportEndpointsTest.php gap found
// earlier. These are the first real tests for BackupController and the
// backup:run artisan command.
class BackupRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    // #82/#83: scheduled vs system(cron)-triggered backup both go through
    // the same backup:run command with a different --trigger value - this
    // covers the command itself (both entry points call it identically).
    public function test_backup_run_command_creates_a_verified_data_backup_with_real_rows(): void
    {
        Storage::fake('local');
        $student = Student::factory()->create();
        $case = CaseFile::factory()->create(['student_id' => $student->id]);
        Referral::factory()->create(['case_id' => $case->id, 'student_id' => $student->id]);

        $this->artisan('backup:run', ['--type' => 'data', '--trigger' => 'scheduled'])
            ->assertExitCode(0);

        $record = BackupRecord::where('type', 'data')->latest()->first();
        $this->assertNotNull($record);
        $this->assertSame('completed', $record->status);
        $this->assertTrue($record->verified);
        $this->assertSame('scheduled', $record->trigger);
        $this->assertGreaterThanOrEqual(1, $record->row_counts['cases']);
        $this->assertGreaterThanOrEqual(1, $record->row_counts['referrals']);
        Storage::disk('local')->assertExists($record->file_path);
    }

    public function test_backup_run_command_records_system_triggered_backup_with_initiating_user(): void
    {
        Storage::fake('local');
        $admin = $this->admin();

        $this->artisan('backup:run', ['--type' => 'config', '--trigger' => 'manual', '--user' => $admin->id])
            ->assertExitCode(0);

        $record = BackupRecord::where('type', 'config')->latest()->first();
        $this->assertSame($admin->id, $record->initiated_by_user_id);
        $this->assertTrue($record->verified);
    }

    public function test_admin_can_trigger_a_manual_backup_through_the_api(): void
    {
        Storage::fake('local');
        $this->actingAs($this->admin());

        $res = $this->postJson('/api/backups/run', ['type' => 'all'])->assertOk();

        $this->assertSame('Backup completed.', $res->json('message'));
        $this->assertCount(2, $res->json('backups'));
        $this->assertSame(2, BackupRecord::count());
    }

    public function test_backup_run_rejects_an_invalid_type(): void
    {
        $this->actingAs($this->admin())
            ->postJson('/api/backups/run', ['type' => 'bogus'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    public function test_backup_index_lists_records_and_filters_by_type(): void
    {
        BackupRecord::factory()->create(['type' => 'data']);
        BackupRecord::factory()->create(['type' => 'config']);
        $this->actingAs($this->admin());

        $this->getJson('/api/backups')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/backups?type=config')->assertOk()->assertJsonCount(1, 'data');
    }

    // #84: restore from the most recent verified backup.
    public function test_restore_data_requires_a_verified_backup(): void
    {
        BackupRecord::factory()->create(['type' => 'data', 'status' => 'completed', 'verified' => false]);
        $this->actingAs($this->admin());

        $this->postJson('/api/backups/restore-data', ['confirm' => 'anything'])->assertStatus(404);
    }

    public function test_restore_data_requires_the_exact_confirmation_text(): void
    {
        Storage::fake('local');
        $backup = BackupRecord::factory()->create([
            'type' => 'data', 'status' => 'completed', 'verified' => true,
            'file_path' => 'backups/data_test.json',
        ]);
        Storage::disk('local')->put($backup->file_path, json_encode(['cases' => []]));
        $this->actingAs($this->admin());

        $this->postJson('/api/backups/restore-data', ['confirm' => 'wrong-text'])
            ->assertStatus(422)
            ->assertJson(['message' => "Confirmation text does not match. Expected: RESTORE-{$backup->id}"]);
    }

    public function test_restore_data_rejects_when_the_backup_file_no_longer_exists_on_disk(): void
    {
        Storage::fake('local');
        $backup = BackupRecord::factory()->create([
            'type' => 'data', 'status' => 'completed', 'verified' => true,
            'file_path' => 'backups/missing.json',
        ]);
        $this->actingAs($this->admin());

        $this->postJson('/api/backups/restore-data', ['confirm' => "RESTORE-{$backup->id}"])
            ->assertStatus(404)
            ->assertJson(['message' => 'Backup file no longer exists on disk.']);
    }

    public function test_restore_data_from_the_latest_verified_backup_replaces_current_rows(): void
    {
        Storage::fake('local');
        $student = Student::factory()->create();
        $case = CaseFile::factory()->create(['student_id' => $student->id, 'status' => 'open']);
        Referral::factory()->create(['case_id' => $case->id, 'student_id' => $student->id]);

        // Snapshot the data as it exists now.
        $this->artisan('backup:run', ['--type' => 'data'])->assertExitCode(0);
        $backup = BackupRecord::where('type', 'data')->latest()->first();

        // Mutate the live data after the backup was taken.
        $case->update(['status' => 'closed']);
        $caseIdAfterBackup = $case->id;

        $this->actingAs($this->admin())
            ->postJson('/api/backups/restore-data', ['confirm' => "RESTORE-{$backup->id}"])
            ->assertOk()
            ->assertJson(['message' => "Data restored from backup #{$backup->id}."]);

        $this->assertSame('open', CaseFile::find($caseIdAfterBackup)->status);
    }

    public function test_restore_data_uses_the_most_recent_verified_backup_not_an_older_one(): void
    {
        Storage::fake('local');
        $older = BackupRecord::factory()->create([
            'type' => 'data', 'status' => 'completed', 'verified' => true,
            'file_path' => 'backups/older.json', 'created_at' => now()->subDay(),
        ]);
        Storage::disk('local')->put($older->file_path, json_encode(['cases' => []]));

        $newer = BackupRecord::factory()->create([
            'type' => 'data', 'status' => 'completed', 'verified' => true,
            'file_path' => 'backups/newer.json', 'created_at' => now(),
        ]);
        Storage::disk('local')->put($newer->file_path, json_encode(['cases' => []]));

        $this->actingAs($this->admin())
            ->postJson('/api/backups/restore-data', ['confirm' => "RESTORE-{$newer->id}"])
            ->assertOk()
            ->assertJson(['message' => "Data restored from backup #{$newer->id}."]);

        // The confirmation text is tied to the specific backup id picked by
        // "latest verified" - the older backup's id would not have matched.
        $this->assertNotEquals($older->id, $newer->id);
    }

    public function test_restore_config_requires_a_verified_backup(): void
    {
        $this->actingAs($this->admin());
        $this->postJson('/api/backups/restore-config', ['confirm' => 'anything'])->assertStatus(404);
    }

    public function test_restore_config_restores_users_without_truncating_the_table(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $other = User::factory()->create(['role' => 'faculty', 'name' => 'Original Name']);

        $this->artisan('backup:run', ['--type' => 'config'])->assertExitCode(0);
        $backup = BackupRecord::where('type', 'config')->latest()->first();

        $other->update(['name' => 'Changed After Backup']);
        $newUserId = User::factory()->create(['role' => 'faculty'])->id;

        $this->actingAs($admin)
            ->postJson('/api/backups/restore-config', ['confirm' => "RESTORE-{$backup->id}"])
            ->assertOk()
            ->assertJson(['message' => "Configuration restored from backup #{$backup->id}."]);

        // upsert-by-id, never truncate: the name reverts, and the user
        // created after the backup is untouched because restoreConfig only
        // reconciles rows that were present in the payload.
        $this->assertSame('Original Name', User::find($other->id)->name);
        $this->assertNotNull(User::find($newUserId), 'restoreConfig must never truncate users');
    }

    public function test_backup_and_restore_routes_require_admin_role(): void
    {
        $faculty = User::factory()->create(['role' => 'faculty', 'is_active' => true]);
        $this->actingAs($faculty);

        $this->postJson('/api/backups/run', ['type' => 'all'])->assertStatus(403);
        $this->postJson('/api/backups/restore-data', ['confirm' => 'x'])->assertStatus(403);
        $this->postJson('/api/backups/restore-config', ['confirm' => 'x'])->assertStatus(403);
        $this->getJson('/api/backups')->assertStatus(403);
    }
}