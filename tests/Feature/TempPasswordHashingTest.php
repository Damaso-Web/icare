<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\User;
use App\Notifications\ReferredStudentNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

// B643 (10/30/2026): temp_password used to be stored in plain text, and an
// admin could retrieve it again at any time via "View Temp Password" - a DB
// leak would hand out live, working passwords. It's now a hash, like the
// real `password` column, shown to the admin exactly once (at creation or
// reset) and never again.
class TempPasswordHashingTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_student_stores_a_hashed_temp_password_not_plaintext(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $res = $this->postJson('/api/students', [
            'student_id' => '3000001', 'first_name' => 'Juan', 'last_name' => 'Cruz', 'year_level' => '1st Year', 'college' => 'College of Information Sciences',
        ])->assertCreated();

        $plaintext = $res->json('temp_password');
        $this->assertNotEmpty($plaintext);

        $student = Student::where('student_id', '3000001')->first();
        $this->assertNotSame($plaintext, $student->temp_password);
        $this->assertTrue(Hash::check($plaintext, $student->temp_password));
        // The real login password works with the same value shown to the admin.
        $this->assertTrue(Hash::check($plaintext, $student->password));
    }

    public function test_view_temp_password_no_longer_returns_the_plaintext(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $create = $this->postJson('/api/students', [
            'student_id' => '3000002', 'first_name' => 'Ana', 'last_name' => 'Reyes', 'year_level' => '1st Year', 'college' => 'College of Information Sciences',
        ])->assertCreated();
        $student = Student::where('student_id', '3000002')->first();

        $res = $this->getJson("/api/students/{$student->id}/temp-password")->assertOk();
        $this->assertTrue($res->json('pending'));
        $this->assertArrayNotHasKey('temp_password', $res->json());
        $this->assertNotSame($create->json('temp_password'), $res->json('message'));
    }

    public function test_view_temp_password_reports_404_once_the_student_has_logged_in(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $student = Student::create([
            'student_id' => '3000003', 'first_name' => 'Mia', 'last_name' => 'Santos', 'year_level' => '1st Year', 'college' => 'College of Information Sciences',
            'password' => bcrypt('whatever'), 'temp_password' => null, 'must_change_password' => false, 'is_active' => true,
        ]);

        $this->getJson("/api/students/{$student->id}/temp-password")->assertStatus(404);
    }

    public function test_admin_reset_password_stores_a_hashed_temp_password(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $student = Student::factory()->create();

        $res = $this->postJson("/api/students/{$student->id}/reset-password", [
            'reason' => 'identity_verified',
        ])->assertOk();

        $plaintext = $res->json('temp_password');
        $this->assertNotEmpty($plaintext);
        $student->refresh();
        $this->assertNotSame($plaintext, $student->temp_password);
        $this->assertTrue(Hash::check($plaintext, $student->temp_password));
    }

    // The referral email used to read the student's EXISTING temp_password
    // back out of storage - now impossible since it's a hash. The referral
    // flow must refresh it to a fresh value and hand that straight to the
    // notification instead.
    public function test_referring_a_pending_student_emails_a_fresh_working_password(): void
    {
        Notification::fake();
        $faculty = User::factory()->create(['role' => 'faculty', 'college' => 'College of Information Sciences', 'department' => 'BS Information Technology']);
        $student = Student::create([
            'student_id' => '3000004', 'first_name' => 'Leo', 'last_name' => 'Dizon', 'year_level' => '1st Year',
            'college' => 'College of Information Sciences', 'program' => 'BS Information Technology',
            'password' => Hash::make('old-temp'), 'temp_password' => Hash::make('old-temp'),
            'must_change_password' => true, 'is_active' => true,
        ]);
        Sanctum::actingAs($faculty);

        $this->postJson('/api/referrals', [
            'student_id' => $student->id, 'referral_type' => 'counseling', 'nature_of_concern' => 'Needs someone to talk to.',
        ])->assertCreated();

        $student->refresh();
        // The old password's hash is gone - a new one was generated and stored.
        $this->assertFalse(Hash::check('old-temp', $student->password));

        Notification::assertSentTo($student, ReferredStudentNotification::class, function ($n) use ($student) {
            $this->assertNotEmpty($n->freshTempPassword);
            return Hash::check($n->freshTempPassword, $student->password);
        });
    }

    public function test_referring_a_student_who_already_set_their_password_sends_no_password(): void
    {
        Notification::fake();
        $faculty = User::factory()->create(['role' => 'faculty', 'college' => 'College of Information Sciences', 'department' => 'BS Information Technology']);
        $student = Student::create([
            'student_id' => '3000005', 'first_name' => 'Pia', 'last_name' => 'Gomez', 'year_level' => '1st Year',
            'college' => 'College of Information Sciences', 'program' => 'BS Information Technology',
            'password' => Hash::make('own-password'), 'temp_password' => null,
            'must_change_password' => false, 'is_active' => true,
        ]);
        Sanctum::actingAs($faculty);

        $this->postJson('/api/referrals', [
            'student_id' => $student->id, 'referral_type' => 'counseling', 'nature_of_concern' => 'Needs someone to talk to.',
        ])->assertCreated();

        Notification::assertSentTo($student, ReferredStudentNotification::class, fn($n) => $n->freshTempPassword === null);
        // Their own password must be untouched.
        $this->assertTrue(Hash::check('own-password', $student->refresh()->password));
    }
}