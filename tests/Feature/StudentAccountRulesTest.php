<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Student;
use App\Support\EmailCheck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StudentAccountRulesTest extends TestCase
{
    use RefreshDatabase;

    private function student(): Student
    {
        return Student::create([
            'student_id' => '2312948', 'first_name' => 'Sunshine', 'last_name' => 'Galo', 'year_level' => '2nd Year',
            'college' => 'College of Medicine (CM)', 'program' => 'BS Medical Technology', 'section' => 'A',
            'password' => Hash::make('x'), 'is_active' => true, 'email' => 'sun@gmail.com',
        ]);
    }

    public function test_email_must_look_complete(): void
    {
        foreach (['sunshine@gmail.co', 'sunshine@gmail', 'sunshine@gmail.con', 'sunshine@gmial.com', 'sunshine@', 'a b@x.com', 'a@b.c1'] as $bad) {
            $this->assertNotNull(EmailCheck::problem($bad), $bad);
        }
        foreach (['sunshine@gmail.com', 'a.b@yahoo.com.ph', 'x@school.edu.ph', 'x@outlook.com'] as $ok) {
            $this->assertNull(EmailCheck::problem($ok), $ok);
        }

        Sanctum::actingAs($this->student(), [], 'student');
        $this->putJson('/api/student/profile', ['email' => 'sunshine@gmail.co'])->assertStatus(422)->assertJsonValidationErrors('email');
        $this->putJson('/api/student/profile', ['email' => 'sunshine@gmail.com'])->assertOk();
    }

    public function test_birthdate_address_and_achievements_rules(): void
    {
        Sanctum::actingAs($this->student(), [], 'student');
        $this->putJson('/api/student/profile', ['birthdate' => now()->toDateString()])->assertStatus(422)->assertJsonValidationErrors('birthdate');
        $this->putJson('/api/student/profile', ['birthdate' => now()->addDay()->toDateString()])->assertStatus(422)->assertJsonValidationErrors('birthdate');
        // far too young is refused; 15-17 (accelerated students) is allowed; adults fine
        $this->putJson('/api/student/profile', ['birthdate' => now()->subYears(10)->toDateString()])->assertStatus(422)->assertJsonValidationErrors('birthdate');
        $this->putJson('/api/student/profile', ['birthdate' => now()->subYears(17)->subDay()->toDateString()])->assertOk();
        $this->putJson('/api/student/profile', ['birthdate' => now()->subYears(20)->toDateString()])->assertOk();

        $this->putJson('/api/student/profile', ['address' => '12345'])->assertStatus(422)->assertJsonValidationErrors('address');
        $this->putJson('/api/student/profile', ['address' => 'Km 5, La Trinidad, Benguet'])->assertOk();

        $this->putJson('/api/student/profile', ['elementary_achievements' => '2024'])->assertStatus(422)->assertJsonValidationErrors('elementary_achievements');
        $this->putJson('/api/student/profile', ['senior_high_school' => '12345'])->assertStatus(422)->assertJsonValidationErrors('senior_high_school');
        $this->putJson('/api/student/profile', ['elementary_achievements' => 'With Honors', 'senior_high_school' => 'Benguet National High School'])->assertOk();
    }

    public function test_names_with_enye_are_saved(): void
    {
        Sanctum::actingAs($this->student(), [], 'student');
        $this->putJson('/api/student/profile', ['first_name' => 'Niño', 'last_name' => 'Muñoz', 'birthplace' => 'Peñablanca', 'nationality' => 'Pilipiño'])
            ->assertOk()->assertJsonPath('first_name', 'Niño');
        $this->assertSame('Muñoz', Student::first()->last_name);
    }

    public function test_dashboard_carries_the_same_student_record_and_the_current_term(): void
    {
        $s = $this->student();
        Sanctum::actingAs($s, [], 'student');
        AcademicYear::query()->delete();
        $y = now('Asia/Manila')->year;
        $ay = AcademicYear::create([
            'start_year' => $y, 'year_start' => ($y - 1) . '-06-01', 'year_end' => ($y + 1) . '-05-31',
            'first_sem_start' => ($y - 1) . '-08-01', 'first_sem_end' => ($y - 1) . '-12-20',
            'second_sem_start' => ($y - 1) . '-12-21', 'second_sem_end' => ($y + 1) . '-05-31',
            'midyear_start' => ($y + 1) . '-06-01', 'midyear_end' => ($y + 1) . '-07-31',
        ]);
        $ay->forceFill(['is_current' => true])->save();

        $r = $this->getJson('/api/student/dashboard')->assertOk();
        $r->assertJsonPath('student.program', 'BS Medical Technology')->assertJsonPath('student.section', 'A')
            ->assertJsonPath('term.academic_year', $y . '–' . ($y + 1))->assertJsonPath('term.semester', '2nd Semester');

        // a shift to another program shows on the next load
        $s->update(['program' => 'BS Nursing']);
        $this->getJson('/api/student/dashboard')->assertJsonPath('student.program', 'BS Nursing');
    }
}