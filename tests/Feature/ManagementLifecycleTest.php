<?php

namespace Tests\Feature;

use App\Models\College;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ManagementLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $u = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        Sanctum::actingAs($u);
        return $u;
    }

    public function test_status_change_needs_a_reason_allowed_for_that_action(): void
    {
        $this->admin();
        $c = College::create(['name' => 'College of Testing', 'abbrev' => 'CT']);

        $this->postJson("/api/management/colleges/{$c->id}/status", ['status' => 'inactive'])->assertStatus(422);
        // An archive reason is not valid for a deactivation.
        $this->postJson("/api/management/colleges/{$c->id}/status", ['status' => 'inactive', 'reason' => 'merged'])->assertStatus(422);
        // "Other" needs a note.
        $this->postJson("/api/management/colleges/{$c->id}/status", ['status' => 'inactive', 'reason' => 'other'])->assertStatus(422);

        $this->postJson("/api/management/colleges/{$c->id}/status", ['status' => 'inactive', 'reason' => 'under_reorganization'])->assertOk();
        $this->assertSame('inactive', $c->fresh()->status);

        $this->postJson("/api/management/colleges/{$c->id}/status", ['status' => 'active', 'reason' => 'review_completed'])->assertOk();
    }

    public function test_archive_is_blocked_while_students_use_the_college_and_edit_is_blocked_when_not_active(): void
    {
        $this->admin();
        $c = College::create(['name' => 'College of Linked', 'abbrev' => 'CL']);
        Student::create([
            'student_id' => '2300009', 'first_name' => 'A', 'last_name' => 'B', 'year_level' => '1st Year',
            'college' => 'College of Linked', 'program' => 'BSIT', 'password' => Hash::make('x'), 'is_active' => true,
        ]);

        $this->postJson("/api/management/colleges/{$c->id}/status", ['status' => 'archived', 'reason' => 'merged'])->assertStatus(422);
        $this->assertSame('active', $c->fresh()->status ?: 'active');

        $this->postJson("/api/management/colleges/{$c->id}/status", ['status' => 'inactive', 'reason' => 'pending_merger'])->assertOk();
        $this->putJson("/api/management/colleges/{$c->id}", ['name' => 'Renamed College', 'abbrev' => 'RC'])->assertStatus(422);
    }

    public function test_delete_reasons_are_only_the_three_for_mistakes(): void
    {
        $this->admin();
        $c = College::create(['name' => 'College of Mistake', 'abbrev' => 'CM']);
        $this->deleteJson("/api/management/colleges/{$c->id}", ['reason' => 'no_longer_offered'])->assertStatus(422);
        $this->deleteJson("/api/management/colleges/{$c->id}", ['reason' => 'created_error'])->assertOk();
    }

    private function term(int $y): array
    {
        return [
            'start_year' => $y, 'year_start' => "$y-08-01", 'year_end' => ($y + 1) . '-07-31',
            'first_sem_start' => "$y-08-01", 'first_sem_end' => "$y-12-31",
            'second_sem_start' => ($y + 1) . '-01-01', 'second_sem_end' => ($y + 1) . '-05-31',
            'midyear_start' => ($y + 1) . '-06-01', 'midyear_end' => ($y + 1) . '-07-31',
        ];
    }

    public function test_school_calendar_current_term_overlap_and_restrictions(): void
    {
        $this->admin();
        $a = $this->postJson('/api/management/academic-years', $this->term(2031))->assertCreated()->json('id');
        // A term already exists (seeded), so a new one starts as not current.
        $this->assertFalse(\App\Models\AcademicYear::find($a)->is_current);
        $this->postJson("/api/management/academic-years/$a/current")->assertOk();
        $this->assertSame(1, \App\Models\AcademicYear::where('is_current', true)->count());

        // Overlapping years are refused with a clear message.
        $bad = $this->term(2032);
        $bad['year_start'] = '2032-01-01'; $bad['first_sem_start'] = '2032-01-01';
        $this->postJson('/api/management/academic-years', $bad)->assertStatus(422)->assertJsonValidationErrors('year_start');

        $b = $this->postJson('/api/management/academic-years', $this->term(2032))->assertCreated()->json('id');
        $this->assertFalse(\App\Models\AcademicYear::find($b)->is_current);

        // The current term can be neither archived nor deleted until another is set as current.
        $this->postJson("/api/management/academic-years/$a/status", ['status' => 'archived', 'reason' => 'term_ended'])->assertStatus(422);
        $this->deleteJson("/api/management/academic-years/$a", ['reason' => 'created_error'])->assertStatus(422);

        $this->postJson("/api/management/academic-years/$b/current")->assertOk();
        $this->assertFalse(\App\Models\AcademicYear::find($a)->is_current);
        $this->postJson("/api/management/academic-years/$a/status", ['status' => 'archived', 'reason' => 'merged'])->assertStatus(422);
        $this->postJson("/api/management/academic-years/$a/status", ['status' => 'archived', 'reason' => 'term_ended'])->assertOk();
        // Archived is read-only.
        $this->putJson("/api/management/academic-years/$a", $this->term(2031))->assertStatus(422);
        $this->deleteJson("/api/management/academic-years/$a", ['reason' => 'duplicate'])->assertOk();
    }
}