<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

// B#, 11/4/2026: same fix as DeanAccountToolsTest's masterlist test, extended
// to the Dept Chair's own Faculty-page upload (FacultyController::
// scopeImportRow(), a separate override from UserController's, used only by
// Dept Chair uploads - a Dean's Faculty uploads go through UserController
// instead). A mismatched College OR Department is now REJECTED, not
// silently relabeled into the Dept Chair's own college/department.
class DeptChairMasterlistUploadTest extends TestCase
{
    use RefreshDatabase;

    private function csv(array $rows): UploadedFile
    {
        $lines = ['Last Name,First Name,Middle Name,Suffix,Email Address,Employee ID,College,Department,Contact Number'];
        foreach ($rows as $r) $lines[] = implode(',', $r);
        return UploadedFile::fake()->createWithContent('faculty.csv', implode("\n", $lines));
    }

    private function chair(): User
    {
        return User::factory()->create([
            'role' => 'dept_chair',
            'college' => 'College of Information Sciences',
            'department' => 'BS Information Technology',
        ]);
    }

    public function test_dept_chair_masterlist_upload_is_scoped_to_their_own_college_and_department(): void
    {
        $chair = $this->chair();
        Sanctum::actingAs($chair);

        $file = $this->csv([
            // blank College/Department (the real template has neither
            // column) still defaults to the Dept Chair's own college/department
            ['Santos', 'Mia', '', '', 'mia.santos@bsu.edu.ph', 'F-300', '', '', ''],
            // a different COLLEGE is rejected, not silently relabeled
            ['Reyes', 'Ana', '', '', 'ana.reyes@bsu.edu.ph', 'F-301', 'College of Business', '', ''],
            // the same college but a different DEPARTMENT is also rejected
            ['Cruz', 'Jun', '', '', 'jun.cruz@bsu.edu.ph', 'F-302', 'College of Information Sciences', 'BS Computer Science', ''],
        ]);

        $res = $this->postJson('/api/faculty/import-preview', ['file' => $file])->assertOk();
        $preview = $res->json('preview');

        $this->assertTrue($preview[0]['valid'], 'reasons: ' . json_encode($preview[0]['reasons']));

        $this->assertFalse($preview[1]['valid']);
        $this->assertStringContainsString('College of Business', $preview[1]['reasons'][0]);
        $this->assertSame('College of Business', $preview[1]['college']);

        $this->assertFalse($preview[2]['valid']);
        $this->assertStringContainsString('BS Computer Science', $preview[2]['reasons'][0]);
        $this->assertSame('BS Computer Science', $preview[2]['department']);

        $token = $res->json('token');
        $this->postJson('/api/faculty/import-confirm', ['token' => $token, 'decisions' => [0 => 'create', 1 => 'create', 2 => 'create']])->assertOk();

        $mia = User::where('email', 'mia.santos@bsu.edu.ph')->first();
        $this->assertNotNull($mia);
        $this->assertSame('College of Information Sciences', $mia->college);
        $this->assertSame('BS Information Technology', $mia->department);

        $this->assertNull(User::where('email', 'ana.reyes@bsu.edu.ph')->first());
        $this->assertNull(User::where('email', 'jun.cruz@bsu.edu.ph')->first());
    }
}