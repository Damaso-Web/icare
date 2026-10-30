<?php

namespace Tests\Feature;

use App\Models\CaseFile;
use App\Models\Document;
use App\Models\Student;
use App\Models\User;
use App\Support\CloudinaryClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

/**
 * B642 (10/31/2026) regression: cross-unit document access.
 *
 * Confirmed before the fix: an SDU Head requesting a non-confidential
 * document attached to a TMDU case received 200 on both show and download.
 * The fix applies the same unit-scope policy to upload, show, download
 * and destroy - these tests lock that in plus the allowed controls.
 */
class DocumentCrossUnitAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // CloudinaryClient is what actually uploads/fetches bytes - swap for
        // a stub so tests don't hit the network. Any call not explicitly
        // stubbed fails loudly.
        $cloud = Mockery::mock(CloudinaryClient::class);
        $cloud->shouldReceive('upload')->andReturnUsing(function ($path, $folder) {
            return ['public_id' => "{$folder}/test", 'resource_type' => 'raw', 'format' => 'pdf'];
        });
        $cloud->shouldReceive('fetch')->andReturn('PDF-CONTENT-BYTES');
        $cloud->shouldReceive('delete')->andReturn(true);
        $this->app->instance(CloudinaryClient::class, $cloud);
    }

    private function user(string $role, array $extra = []): User
    {
        return User::factory()->create(array_merge(['role' => $role, 'is_active' => true], $extra));
    }

    private function student(string $id = '0001'): Student
    {
        return Student::factory()->create(['student_id' => $id]);
    }

    private function tmduCase(): CaseFile
    {
        return CaseFile::factory()->create([
            'student_id'   => $this->student('TMDU-1')->id,
            'current_unit' => 'TMDU',
        ]);
    }

    private function sduCase(): CaseFile
    {
        return CaseFile::factory()->create([
            'student_id'   => $this->student('SDU-1')->id,
            'current_unit' => 'SDU',
        ]);
    }

    private function document(CaseFile $case, bool $confidential = false, ?int $uploadedBy = null): Document
{
    return Document::create([
        'uploaded_by_user_id' => $uploadedBy ?? User::factory()->create()->id,
        'documentable_type'   => CaseFile::class,
        'documentable_id'     => $case->id,
        'original_filename'   => 'test.pdf',
        'stored_filename'     => 'cloud/test',
        'disk'                => 'cloudinary',
        'cloud_public_id'     => 'cloud/test',
        'cloud_resource_type' => 'raw',
        'cloud_format'        => 'pdf',
        'mime_type'           => 'application/pdf',
        'file_size'           => 100,
        'document_type'       => 'supporting_document',
        'is_confidential'     => $confidential,
    ]);
}

    // ---------- show / download: cross-unit reads are 403 ----------

    public function test_sdu_head_cannot_show_a_non_confidential_document_attached_to_a_tmdu_case(): void
    {
        $doc = $this->document($this->tmduCase());
        Sanctum::actingAs($this->user('sdu_head'));

        $this->getJson("/api/documents/{$doc->id}")->assertStatus(403);
    }

    public function test_sdu_head_cannot_download_a_non_confidential_document_attached_to_a_tmdu_case(): void
    {
        $doc = $this->document($this->tmduCase());
        Sanctum::actingAs($this->user('sdu_head'));

        $this->get("/api/documents/{$doc->id}/download")->assertStatus(403);
    }

    public function test_tmdu_staff_cannot_show_a_non_confidential_document_attached_to_an_sdu_case(): void
    {
        $doc = $this->document($this->sduCase());
        Sanctum::actingAs($this->user('tmdu_staff'));

        $this->getJson("/api/documents/{$doc->id}")->assertStatus(403);
    }

    // ---------- confidential boundary (cross-cutting) ----------

    public function test_sdu_head_cannot_show_a_confidential_document_even_on_their_own_unit(): void
    {
        $doc = $this->document($this->sduCase(), confidential: true);
        Sanctum::actingAs($this->user('sdu_head'));

        $this->getJson("/api/documents/{$doc->id}")->assertStatus(403);
    }

    // ---------- allowed controls: the fix must not break legitimate flows ----------

    public function test_sdu_head_can_show_a_document_on_an_sdu_case(): void
    {
        $doc = $this->document($this->sduCase());
        Sanctum::actingAs($this->user('sdu_head'));

        $this->getJson("/api/documents/{$doc->id}")
            ->assertOk()
            ->assertJsonPath('id', $doc->id);
    }

    public function test_tmdu_staff_can_download_a_document_on_a_tmdu_case(): void
    {
        $doc = $this->document($this->tmduCase());
        Sanctum::actingAs($this->user('tmdu_staff'));

        $response = $this->get("/api/documents/{$doc->id}/download");
        $response->assertOk();
        $this->assertSame('PDF-CONTENT-BYTES', $response->getContent());
    }

    public function test_admin_can_show_any_document(): void
    {
        $doc = $this->document($this->tmduCase(), confidential: true);
        Sanctum::actingAs($this->user('admin'));

        $this->getJson("/api/documents/{$doc->id}")->assertOk();
    }

    public function test_gcu_staff_can_show_any_non_confidential_document(): void
    {
        $doc = $this->document($this->tmduCase());
        Sanctum::actingAs($this->user('gcu_staff'));

        $this->getJson("/api/documents/{$doc->id}")->assertOk();
    }

    // ---------- upload: blind writes are refused, DB stays unchanged ----------

    public function test_sdu_head_cannot_upload_a_document_onto_a_tmdu_case(): void
    {
        $case = $this->tmduCase();
        Sanctum::actingAs($this->user('sdu_head'));

        $before = Document::count();

        $this->postJson('/api/documents/upload', [
            'documentable_type' => 'case',
            'documentable_id'   => $case->id,
            'document_type'     => 'supporting_document',
            'file'              => UploadedFile::fake()->create('note.pdf', 10, 'application/pdf'),
        ])->assertStatus(403);

        $this->assertSame($before, Document::count(), 'Denied upload must not leave a row.');
    }

    public function test_sdu_head_can_upload_a_document_onto_an_sdu_case(): void
    {
        $case = $this->sduCase();
        Sanctum::actingAs($this->user('sdu_head'));

        $this->postJson('/api/documents/upload', [
            'documentable_type' => 'case',
            'documentable_id'   => $case->id,
            'document_type'     => 'supporting_document',
            'file'              => UploadedFile::fake()->create('note.pdf', 10, 'application/pdf'),
        ])->assertCreated();

        $this->assertSame(1, Document::where('documentable_id', $case->id)->count());
    }

    public function test_sdu_head_cannot_flag_a_document_as_confidential_on_upload(): void
    {
        // An SDU Head can only attach to SDU cases; but they still must not
        // be able to lock themselves out of their own upload by flipping
        // is_confidential. The controller silently coerces it to false.
        $case = $this->sduCase();
        Sanctum::actingAs($this->user('sdu_head'));

        $this->postJson('/api/documents/upload', [
            'documentable_type' => 'case',
            'documentable_id'   => $case->id,
            'document_type'     => 'supporting_document',
            'is_confidential'   => true,
            'file'              => UploadedFile::fake()->create('note.pdf', 10, 'application/pdf'),
        ])->assertCreated();

        $this->assertFalse((bool) Document::where('documentable_id', $case->id)->first()->is_confidential);
    }

    // ---------- destroy: cross-unit delete refused, DB/file unchanged ----------

    public function test_sdu_head_cannot_delete_a_cross_unit_document_even_if_they_uploaded_it(): void
    {
        $sdu = $this->user('sdu_head');
        // Simulate a legacy row where the SDU Head somehow is the uploader
        // of a document on a TMDU case.
        $doc = $this->document($this->tmduCase(), uploadedBy: $sdu->id);
        Sanctum::actingAs($sdu);

        $this->deleteJson("/api/documents/{$doc->id}")->assertStatus(403);
        $this->assertNotNull(Document::find($doc->id), 'Denied delete must not remove the row.');
    }

    public function test_uploader_can_delete_their_own_same_unit_document(): void
    {
        $staff = $this->user('gcu_staff');
        $doc = $this->document($this->tmduCase(), uploadedBy: $staff->id);
        Sanctum::actingAs($staff);

        $this->deleteJson("/api/documents/{$doc->id}")->assertOk();
        $this->assertNull(Document::find($doc->id));
    }
}