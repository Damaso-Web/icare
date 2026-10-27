<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\TestingRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

// B108: Family Information, Siblings Information and Educational Attainment
// are meant to be GCU-only, but GET /students/{id} returned the whole
// Student record to anyone who could open the page - including TMDU staff,
// who can reach it for a student referred to them (authorizeTmduScope()).
class StudentSifFieldScopeTest extends TestCase
{
    use RefreshDatabase;

    private function student(): Student
    {
        return Student::factory()->create([
            'father_first_name' => 'Juan', 'father_occupation' => 'Driver',
            'mother_first_name' => 'Maria', 'mother_occupation' => 'Vendor',
            'siblings' => json_encode([['first_name' => 'Ana', 'age' => '10']]),
            'elementary_school' => 'Batangas ES', 'elementary_achievements' => 'Honor roll',
            'senior_high_school' => 'Batangas SHS', 'senior_high_achievements' => 'Top 1',
        ]);
    }

    public function test_tmdu_staff_does_not_receive_sif_only_fields(): void
    {
        $student = $this->student();
        TestingRecord::create([
            'case_id' => \App\Models\CaseFile::factory()->create(['student_id' => $student->id])->id,
            'student_id' => $student->id,
            'referred_by_user_id' => User::factory()->create()->id,
            'status' => 'pending',
        ]);

        $tmdu = User::factory()->create(['role' => 'tmdu_staff', 'is_active' => true]);
        Sanctum::actingAs($tmdu);

        $res = $this->getJson("/api/students/{$student->id}")->assertOk();
        $res->assertJsonMissing(['father_first_name' => 'Juan']);
        $this->assertArrayNotHasKey('father_first_name', $res->json());
        $this->assertArrayNotHasKey('elementary_achievements', $res->json());
        $this->assertArrayNotHasKey('senior_high_achievements', $res->json());
        $this->assertArrayNotHasKey('siblings', $res->json());
    }

    public function test_gcu_staff_still_receives_full_sif(): void
    {
        $student = $this->student();
        $gcu = User::factory()->create(['role' => 'gcu_staff', 'is_active' => true]);
        Sanctum::actingAs($gcu);

        $res = $this->getJson("/api/students/{$student->id}")->assertOk();
        $this->assertSame('Juan', $res->json('father_first_name'));
        $this->assertSame('Honor roll', $res->json('elementary_achievements'));
    }
}