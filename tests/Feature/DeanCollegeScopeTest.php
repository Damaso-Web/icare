<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

// B590: a Dean's student lookup (and the referral/complaint it feeds) is
// scoped to their own college - they manage their own college's students,
// not the whole university.
class DeanCollegeScopeTest extends TestCase
{
    use RefreshDatabase;

    private function student(string $id, string $college): Student
    {
        return Student::create([
            'student_id' => $id, 'first_name' => 'Juan', 'last_name' => 'Cruz', 'year_level' => '1st Year',
            'college' => $college, 'program' => 'BSIT', 'password' => bcrypt('x'), 'is_active' => true,
        ]);
    }

    public function test_dean_student_search_only_returns_their_own_college(): void
    {
        $dean = User::factory()->create(['role' => 'dean', 'college' => 'College of Information Sciences']);
        $mine = $this->student('2600001', 'College of Information Sciences');
        $other = $this->student('2600002', 'College of Business');
        Sanctum::actingAs($dean);

        // Even trying to force another college via the query param is ignored.
        $res = $this->getJson('/api/students?search=Cruz&college=College of Business')->assertOk();
        $ids = collect($res->json('data'))->pluck('id')->all();
        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($other->id, $ids);
    }

    public function test_other_staff_student_search_is_not_restricted_by_college(): void
    {
        $staff = User::factory()->create(['role' => 'gcu_staff']);
        $a = $this->student('2600003', 'College of Information Sciences');
        $b = $this->student('2600004', 'College of Business');
        Sanctum::actingAs($staff);

        $res = $this->getJson('/api/students?search=Cruz')->assertOk();
        $ids = collect($res->json('data'))->pluck('id')->all();
        $this->assertContains($a->id, $ids);
        $this->assertContains($b->id, $ids);
    }

    public function test_dean_cannot_refer_a_student_outside_their_college(): void
    {
        Notification::fake();
        $dean = User::factory()->create(['role' => 'dean', 'college' => 'College of Information Sciences']);
        $other = $this->student('2600005', 'College of Business');
        Sanctum::actingAs($dean);

        $this->postJson('/api/referrals', [
            'student_id' => $other->id, 'referral_type' => 'counseling', 'nature_of_concern' => 'Needs someone to talk to.',
        ])->assertStatus(403);
    }

    public function test_dean_can_refer_a_student_in_their_own_college(): void
    {
        Notification::fake();
        $dean = User::factory()->create(['role' => 'dean', 'college' => 'College of Information Sciences']);
        $mine = $this->student('2600006', 'College of Information Sciences');
        Sanctum::actingAs($dean);

        $this->postJson('/api/referrals', [
            'student_id' => $mine->id, 'referral_type' => 'counseling', 'nature_of_concern' => 'Needs someone to talk to.',
        ])->assertCreated();
    }

    public function test_dean_cannot_file_a_complaint_against_a_student_outside_their_college(): void
    {
        Notification::fake();
        \Illuminate\Support\Facades\Storage::fake('public');
        $dean = User::factory()->create(['role' => 'dean', 'college' => 'College of Information Sciences']);
        $other = $this->student('2600007', 'College of Business');
        Sanctum::actingAs($dean);

        $file = fn ($n) => \Illuminate\Http\UploadedFile::fake()->create($n, 10);
        $this->postJson('/api/complaints', [
            'complainant_name' => $dean->name,
            'complainant_address' => 'Batangas City', 'complainee_student_id' => $other->id,
            'violation_type' => 'Bullying', 'incident_date' => now()->subDay()->toDateString(),
            'description' => 'A believable account of what happened.',
            'certification_agreed' => '1',
            'evidence' => [$file('e.pdf')], 'affidavit' => [$file('a.pdf')],
        ])->assertStatus(403);
    }
}