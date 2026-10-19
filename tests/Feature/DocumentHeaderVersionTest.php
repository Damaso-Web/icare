<?php

namespace Tests\Feature;

use App\Models\DocumentSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DocumentHeaderVersionTest extends TestCase
{
    use RefreshDatabase;

    private function as(string $role): User
    {
        $u = User::factory()->create(['role' => $role]);
        Sanctum::actingAs($u);
        return $u;
    }

    private function body(array $o = []): array
    {
        return $o + ['revision_no' => '02', 'effectivity_date' => '2026-10-01', 'ctrl_no_year' => '26', 'ctrl_no_term' => '1'];
    }

    public function test_saving_creates_a_new_version_and_keeps_the_old_one(): void
    {
        $this->as('admin');
        $this->getJson('/api/document-settings/QF-OSS-01')->assertOk();
        $this->putJson('/api/document-settings/QF-OSS-01', $this->body())->assertOk()->assertJsonPath('version_no', 2);

        $this->assertSame(2, DocumentSetting::where('document_code', 'QF-OSS-01')->count());
        $this->assertSame(1, DocumentSetting::where('document_code', 'QF-OSS-01')->where('is_active', true)->count());
        $this->getJson('/api/document-settings/QF-OSS-01')->assertJsonPath('revision_no', '02');
    }

    public function test_unchanged_header_is_rejected(): void
    {
        $this->as('admin');
        $this->putJson('/api/document-settings/QF-OSS-01', $this->body())->assertOk();
        $this->putJson('/api/document-settings/QF-OSS-01', $this->body())->assertStatus(422);
    }

    public function test_set_active_archive_and_delete_rules(): void
    {
        $this->as('admin');
        $this->getJson('/api/document-settings/QF-OSS-03');
        $this->putJson('/api/document-settings/QF-OSS-03', $this->body(['revision_no' => '03']))->assertOk();
        $v1 = DocumentSetting::where('document_code', 'QF-OSS-03')->where('version_no', 1)->first();
        $v2 = DocumentSetting::where('document_code', 'QF-OSS-03')->where('version_no', 2)->first();

        // active version cannot be archived or deleted
        $this->postJson("/api/document-settings/QF-OSS-03/versions/{$v2->id}/archive", ['reason' => 'outdated_format'])->assertStatus(422);
        $this->deleteJson("/api/document-settings/QF-OSS-03/versions/{$v2->id}", ['reason' => 'duplicate'])->assertStatus(422);

        // reason is required and must be from the list
        $this->postJson("/api/document-settings/QF-OSS-03/versions/{$v1->id}/archive", [])->assertStatus(422);
        $this->postJson("/api/document-settings/QF-OSS-03/versions/{$v1->id}/archive", ['reason' => 'nonsense'])->assertStatus(422);

        // set v1 active again, then v2 is the one that can be archived
        $this->postJson("/api/document-settings/QF-OSS-03/versions/{$v1->id}/activate")->assertOk();
        $this->assertSame(1, DocumentSetting::where('document_code', 'QF-OSS-03')->where('is_active', true)->count());
        $this->postJson("/api/document-settings/QF-OSS-03/versions/{$v2->id}/archive", ['reason' => 'replaced_version'])->assertOk();
        $this->assertSame('archived', $v2->fresh()->status);

        // archived cannot be activated
        $this->postJson("/api/document-settings/QF-OSS-03/versions/{$v2->id}/activate")->assertStatus(422);

        // an unused inactive version can be deleted with a reason
        $this->deleteJson("/api/document-settings/QF-OSS-03/versions/{$v2->id}", ['reason' => 'other'])->assertStatus(422);
        $this->deleteJson("/api/document-settings/QF-OSS-03/versions/{$v2->id}", ['reason' => 'created_error'])->assertOk();
        $this->assertNull(DocumentSetting::find($v2->id));
    }

    public function test_only_the_right_role_can_manage(): void
    {
        $this->as('gcu_staff');
        $this->putJson('/api/document-settings/QF-OSS-01', $this->body())->assertStatus(403);
        $this->as('admin');
        $this->putJson('/api/document-settings/QF-TMDU-02', $this->body())->assertStatus(403);
        $this->as('system_admin');
        $this->putJson('/api/document-settings/QF-TMDU-02', $this->body())->assertOk();
    }

    public function test_at_date_returns_the_header_in_force_then(): void
    {
        $this->as('admin');
        $this->getJson('/api/document-settings/QF-OSS-01');
        $this->travel(5)->days();
        $this->putJson('/api/document-settings/QF-OSS-01', $this->body(['revision_no' => '05']))->assertOk();
        $this->getJson('/api/document-settings/QF-OSS-01?at=' . now()->subDays(2)->toDateString())->assertJsonPath('version_no', 1);
        $this->getJson('/api/document-settings/QF-OSS-01?at=' . now()->addDay()->toDateString())->assertJsonPath('version_no', 2);
    }
}