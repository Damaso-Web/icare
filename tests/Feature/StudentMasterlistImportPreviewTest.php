<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * B#, 11/4/2026: reproduces the "Server Error" reported on a real Student
 * masterlist upload. StudentController::importPreview()'s same-name lookup
 * used to crash with "Method Illuminate\Database\Eloquent\Collection::getKey
 * does not exist" whenever the uploaded file contained a first+last name
 * that also exists on an existing student - which is exactly what a real,
 * non-trivial masterlist does. None of the existing tests ever uploaded a
 * file with a name that collides with an existing Student, so this path was
 * never actually exercised before.
 */
class StudentMasterlistImportPreviewTest extends TestCase
{
    use RefreshDatabase;

    private function csv(array $rows): UploadedFile
    {
        $lines = ['Student ID,Last Name,First Name,Middle Name,Sex,Email Address,Contact Number,College,Year Level,Guardian Contact'];
        foreach ($rows as $r) $lines[] = implode(',', $r);
        return UploadedFile::fake()->createWithContent('students.csv', implode("\n", $lines));
    }

    public function test_preview_does_not_crash_when_a_row_shares_a_name_with_an_existing_student(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'gcu_staff']));

        Student::factory()->create([
            'student_id'  => '2000001',
            'first_name'  => 'Juan',
            'middle_name' => 'Santos',
            'last_name'   => 'Dela Cruz',
        ]);

        $file = $this->csv([
            // Same first + last name as the existing student above, but a
            // different middle name - a different person, a different
            // Student ID, and the exact scenario that used to crash.
            ['2000002', 'Dela Cruz', 'Juan', 'Reyes', 'Male', 'juan.delacruz2@bsu.edu.ph', '09171234567', 'College of Information Sciences (CIS)', '1', '09179876543'],
        ]);

        $res = $this->postJson('/api/students/import-preview', ['file' => $file])->assertOk();

        $this->assertSame(1, $res->json('total'));
        $row = $res->json('preview.0');
        $this->assertTrue($row['valid'], 'reasons: ' . json_encode($row['reasons']));
        $this->assertFalse($row['is_duplicate']);
        // Different middle name - first+last+middle no longer matches, so no
        // "Same name" flag either (see the name-matching fix in the same
        // commit).
        $this->assertSame([], $row['name_matches']);
    }

    public function test_preview_flags_same_name_when_first_middle_and_last_all_match(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'gcu_staff']));

        Student::factory()->create([
            'student_id'  => '2000001',
            'first_name'  => 'Juan',
            'middle_name' => 'Santos',
            'last_name'   => 'Dela Cruz',
        ]);

        $file = $this->csv([
            ['2000002', 'Dela Cruz', 'Juan', 'Santos', 'Male', 'juan.delacruz2@bsu.edu.ph', '09171234567', 'College of Information Sciences (CIS)', '1', '09179876543'],
        ]);

        $res = $this->postJson('/api/students/import-preview', ['file' => $file])->assertOk();

        $row = $res->json('preview.0');
        $this->assertCount(1, $row['name_matches']);
        $this->assertSame('2000001', $row['name_matches'][0]['student_id']);
    }
}