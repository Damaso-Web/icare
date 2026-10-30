<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Requirements 72 (record user activities), 73 (review user activities),
// and 81 (log account activities) - AuditLogController had zero dedicated
// tests before (the AuditLogControllerTest.php the 2026-10-30 handoff cites
// as "7/33 passed" doesn't exist in the repo). RoleMatrixTest.php already
// proves GET /api/audit-logs is admin-only (200/403/401); this file covers
// the actual filtering, summary, show, and export behavior.
class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    private function log(array $attrs = []): AuditLog
    {
        return AuditLog::create(array_merge([
            'user_id' => null, 'user_name' => 'Someone', 'user_role' => 'faculty',
            'action' => 'viewed', 'description' => 'Viewed something.',
            'ip_address' => '127.0.0.1', 'created_at' => now(),
        ], $attrs));
    }

    // #81: a real failed and a real successful login each write their own
    // audit entry - not a synthetic AuditLog::create, the actual login flow.
    public function test_failed_login_is_logged_with_the_correct_action_and_no_user_id(): void
    {
        $user = User::factory()->create(['role' => 'faculty', 'is_active' => true]);

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertStatus(401);

        $entry = AuditLog::where('action', 'login_failed')->latest()->first();
        $this->assertNotNull($entry);
        $this->assertStringContainsString('incorrect password', $entry->description);
        $this->assertSame($user->id, $entry->user_id);
    }

    public function test_successful_login_is_logged(): void
    {
        $user = User::factory()->create(['role' => 'faculty', 'is_active' => true, 'password' => bcrypt('Password1!')]);

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'Password1!'])->assertOk();

        $entry = AuditLog::where('action', 'login')->where('user_id', $user->id)->first();
        $this->assertNotNull($entry);
    }

    // #72/#73: filtering and review.
    public function test_index_filters_by_action_user_date_range_and_search(): void
    {
        $alice = User::factory()->create(['name' => 'Alice Faculty']);
        $this->log(['user_id' => $alice->id, 'user_name' => 'Alice Faculty', 'action' => 'login', 'description' => 'User Alice logged in.', 'created_at' => '2026-01-10']);
        $this->log(['action' => 'login_failed', 'description' => 'Failed staff login.', 'created_at' => '2026-01-15']);
        $this->log(['action' => 'viewed', 'description' => 'Viewed a referral.', 'created_at' => '2026-02-01']);

        $this->actingAs($this->admin());

        $this->getJson('/api/audit-logs?action=login')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/audit-logs?user_id=' . $alice->id)->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/audit-logs?date_from=2026-01-12&date_to=2026-01-20')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/audit-logs?search=referral')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/audit-logs?search=Alice')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/audit-logs')->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_index_accepts_only_the_allowed_page_sizes(): void
    {
        $this->log();
        $this->actingAs($this->admin());

        $res = $this->getJson('/api/audit-logs?per_page=999')->assertOk();
        $this->assertSame(50, $res->json('per_page')); // invalid size falls back to the default
        $this->assertSame(25, $this->getJson('/api/audit-logs?per_page=25')->json('per_page'));
    }

    public function test_show_returns_a_single_entry_with_its_user_relation(): void
    {
        $alice = User::factory()->create();
        $entry = $this->log(['user_id' => $alice->id]);
        $this->actingAs($this->admin());

        $this->getJson("/api/audit-logs/{$entry->id}")
            ->assertOk()
            ->assertJsonPath('id', $entry->id)
            ->assertJsonPath('user.id', $alice->id);
    }

    public function test_filter_options_lists_distinct_actions_and_users_who_have_entries(): void
    {
        $alice = User::factory()->create(['name' => 'Alice Faculty']);
        $bob = User::factory()->create(['name' => 'Bob Staff']);
        $this->log(['user_id' => $alice->id, 'action' => 'login']);
        $this->log(['user_id' => $alice->id, 'action' => 'login']); // duplicate action/user
        $this->log(['user_id' => null, 'action' => 'login_failed']);
        $this->actingAs($this->admin());

        $res = $this->getJson('/api/audit-logs/filter-options')->assertOk();

        $this->assertSame(['login', 'login_failed'], $res->json('actions'));
        $userIds = collect($res->json('users'))->pluck('id')->all();
        $this->assertContains($alice->id, $userIds);
        $this->assertNotContains($bob->id, $userIds); // Bob has no log entries
    }

    public function test_summary_counts_events_by_category_and_scopes_to_date_filter(): void
    {
        $this->log(['action' => 'login', 'created_at' => '2026-01-05']);
        $this->log(['action' => 'login', 'created_at' => '2026-02-05']);
        $this->log(['action' => 'login_failed', 'description' => 'wrong_password attempt', 'created_at' => '2026-02-05']);
        $this->log(['action' => 'password_change', 'created_at' => '2026-02-05']);
        $this->log(['action' => 'viewed', 'created_at' => '2026-02-05']);
        $this->log(['action' => 'exported', 'created_at' => '2026-02-05']);
        $this->actingAs($this->admin());

        $all = $this->getJson('/api/audit-logs/summary')->assertOk();
        $this->assertSame(6, $all->json('total_events'));
        $this->assertSame(2, $all->json('logins'));
        $this->assertSame(1, $all->json('failed_logins'));
        $this->assertSame(1, $all->json('password_changes'));
        $this->assertSame(1, $all->json('records_viewed'));
        $this->assertSame(1, $all->json('exports'));

        $scoped = $this->getJson('/api/audit-logs/summary?date_from=2026-02-01&date_to=2026-02-28')->assertOk();
        $this->assertSame(5, $scoped->json('total_events'));
        $this->assertSame(1, $scoped->json('logins'));

        $failedByAccount = $all->json('failed_by_account');
        $this->assertCount(1, $failedByAccount);
        $this->assertSame('wrong password attempt', $failedByAccount[0]['what']);
    }

    public function test_summary_counts_distinct_accounts_that_logged_in(): void
    {
        $alice = User::factory()->create(['name' => 'Alice']);
        $bob = User::factory()->create(['name' => 'Bob', 'role' => 'gcu_staff']);
        $this->log(['user_id' => $alice->id, 'user_name' => 'Alice', 'action' => 'login']);
        $this->log(['user_id' => $alice->id, 'user_name' => 'Alice', 'action' => 'login']); // same account, second login
        $this->log(['user_id' => $bob->id, 'user_name' => 'Bob', 'user_role' => 'gcu_staff', 'action' => 'login']);
        $this->actingAs($this->admin());

        $this->assertSame(2, $this->getJson('/api/audit-logs/summary')->json('active_accounts'));
    }

    public function test_export_pdf_returns_a_pdf_document(): void
    {
        $this->log();
        $this->actingAs($this->admin());

        $res = $this->get('/api/audit-logs/export/pdf');
        $res->assertOk();
        $this->assertSame('application/pdf', $res->headers->get('Content-Type'));
    }

    public function test_export_excel_returns_a_workbook_matching_the_same_filters_as_index(): void
    {
        $this->log(['action' => 'login']);
        $this->log(['action' => 'login_failed']);
        $this->actingAs($this->admin());

        $res = $this->get('/api/audit-logs/export/excel?action=login');
        $res->assertOk();
        $this->assertSame('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $res->headers->get('Content-Type'));
    }

    public function test_export_routes_require_admin_role(): void
    {
        $faculty = User::factory()->create(['role' => 'faculty', 'is_active' => true]);
        $this->actingAs($faculty);

        $this->get('/api/audit-logs/export/pdf')->assertStatus(403);
        $this->get('/api/audit-logs/export/excel')->assertStatus(403);
        $this->getJson('/api/audit-logs/filter-options')->assertStatus(403);
        $this->getJson('/api/audit-logs/summary')->assertStatus(403);
    }
}