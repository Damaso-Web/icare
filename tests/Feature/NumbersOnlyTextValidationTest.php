<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

// B581/B584: a referral's Concern/Reason, and a complaint's Address and
// Narration of Facts, must contain at least one letter - "1234567890" passes
// the existing length/character-set rules but isn't a real answer.
class NumbersOnlyTextValidationTest extends TestCase
{
    use RefreshDatabase;

    private function student(string $id, string $first): Student
    {
        return Student::create([
            'student_id' => $id, 'first_name' => $first, 'last_name' => 'Cruz', 'year_level' => '1st Year',
            'college' => 'CIT', 'program' => 'BSIT', 'password' => Hash::make('x'), 'is_active' => true,
        ]);
    }

    public function test_staff_referral_rejects_numbers_only_reason(): void
    {
        Notification::fake();
        $target = $this->student('2400001', 'Target');
        $u = User::factory()->create(['role' => 'gcu_staff', 'college' => 'CIT', 'is_active' => true]);
        Sanctum::actingAs($u);

        $this->postJson('/api/referrals', [
            'student_id' => $target->id, 'referral_type' => 'counseling', 'nature_of_concern' => '1234567890',
        ])->assertStatus(422)->assertJsonValidationErrors('nature_of_concern');

        $this->postJson('/api/referrals', [
            'student_id' => $target->id, 'referral_type' => 'counseling', 'nature_of_concern' => 'Needs someone to talk to.',
        ])->assertCreated();
    }

    public function test_student_referral_rejects_numbers_only_reason(): void
    {
        Notification::fake();
        $me = $this->student('2400002', 'Me');
        $friend = $this->student('2400003', 'Friend');
        Sanctum::actingAs($me, [], 'student');

        $this->postJson('/api/student/outreach/referrals', [
            'student_id' => $friend->id, 'referral_type' => 'counseling', 'nature_of_concern' => '0123456789',
        ])->assertStatus(422)->assertJsonValidationErrors('nature_of_concern');

        $this->postJson('/api/student/outreach/referrals', [
            'student_id' => $friend->id, 'referral_type' => 'counseling', 'nature_of_concern' => 'Seems very stressed lately.',
        ])->assertCreated();
    }

    public function test_staff_complaint_rejects_numbers_only_address_and_narration(): void
    {
        Notification::fake();
        $complainant = User::factory()->create(['role' => 'gcu_staff', 'college' => 'CIT', 'is_active' => true]);
        $other = $this->student('2400004', 'Other');
        Sanctum::actingAs($complainant);
        Storage::fake('public');

        $file = fn ($n) => \Illuminate\Http\UploadedFile::fake()->create($n, 10);
        $base = [
            'complainant_name' => $complainant->name,
            'complainee_student_id' => $other->id,
            'violation_type' => 'Bullying', 'incident_date' => now()->subDay()->toDateString(),
            'certification_agreed' => '1',
            'evidence' => [$file('e.pdf')], 'affidavit' => [$file('a.pdf')],
        ];

        $this->postJson('/api/complaints', $base + ['complainant_address' => '123456', 'description' => 'A believable account of what happened.'])
            ->assertStatus(422)->assertJsonValidationErrors('complainant_address');

        $this->postJson('/api/complaints', $base + ['complainant_address' => 'Batangas City', 'description' => '1234567890'])
            ->assertStatus(422)->assertJsonValidationErrors('description');

        $this->postJson('/api/complaints', $base + ['complainant_address' => 'Batangas City', 'description' => 'A believable account of what happened.'])
            ->assertCreated();
    }

    public function test_student_complaint_rejects_numbers_only_narration(): void
    {
        Notification::fake();
        $me = $this->student('2400005', 'Me');
        $other = $this->student('2400006', 'Other');
        Sanctum::actingAs($me, [], 'student');
        Storage::fake('public');

        $file = fn ($n) => \Illuminate\Http\UploadedFile::fake()->create($n, 10);
        $base = [
            'complainee_student_id' => $other->id,
            'violation_type' => 'Bullying', 'incident_date' => now()->subDay()->toDateString(),
            'certification_agreed' => '1',
            'evidence' => [$file('e.pdf')], 'affidavit' => [$file('a.pdf')],
        ];

        $this->postJson('/api/student/outreach/complaints', $base + ['complainant_address' => 'Batangas City', 'description' => '9999999999'])
            ->assertStatus(422)->assertJsonValidationErrors('description');

        $this->postJson('/api/student/outreach/complaints', $base + ['complainant_address' => '000000', 'description' => 'A believable account of what happened.'])
            ->assertStatus(422)->assertJsonValidationErrors('complainant_address');

        $this->postJson('/api/student/outreach/complaints', $base + ['complainant_address' => 'Batangas City', 'description' => 'A believable account of what happened.'])
            ->assertCreated();
    }
}