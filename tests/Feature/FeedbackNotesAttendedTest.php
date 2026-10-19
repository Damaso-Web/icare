<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\SessionNote;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FeedbackNotesAttendedTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        $u = User::factory()->create(['role' => 'gcu_staff', 'unit' => 'GCU']);
        Sanctum::actingAs($u);
        return $u;
    }

    private function appt(Student $s, ?Referral $r, CaseFile $c): Appointment
    {
        return Appointment::create([
            'student_id' => $s->id, 'case_id' => $c->id, 'referral_id' => $r?->id,
            'staff_user_id' => User::factory()->create(['role' => 'gcu_staff'])->id,
            'unit' => 'GCU', 'appointment_type' => 'initial_counseling',
            'appointment_date' => now()->addDay()->toDateString(), 'start_time' => '09:00', 'end_time' => '10:00',
            'duration_minutes' => 60, 'status' => 'confirmed', 'request_status' => 'confirmed',
        ]);
    }

    public function test_mark_attended_moves_the_referral_the_appointment_belongs_to(): void
    {
        $this->staff();
        $s = Student::factory()->create();
        $c = CaseFile::factory()->create(['student_id' => $s->id]);
        $r1 = Referral::factory()->create(['student_id' => $s->id, 'case_id' => $c->id, 'status' => 'scheduled']);
        $r2 = Referral::factory()->create(['student_id' => $s->id, 'case_id' => $c->id, 'status' => 'scheduled']);
        $a = $this->appt($s, $r1, $c);   // belongs to the older referral

        $this->postJson("/api/appointments/{$a->id}/check-in")->assertOk()->assertJsonPath('status', 'completed');

        $this->assertSame('in_progress', $r1->fresh()->status);
        $this->assertSame('scheduled', $r2->fresh()->status);
    }

    public function test_cancelled_appointment_cannot_be_marked_attended(): void
    {
        $this->staff();
        $s = Student::factory()->create();
        $c = CaseFile::factory()->create(['student_id' => $s->id]);
        $a = $this->appt($s, null, $c);
        $a->update(['status' => 'cancelled']);

        $this->postJson("/api/appointments/{$a->id}/check-in")->assertStatus(422);
        $this->assertSame('cancelled', $a->fresh()->status);
    }

    public function test_feedback_slip_needs_at_least_one_intervention(): void
    {
        $this->staff();
        $s = Student::factory()->create();
        $c = CaseFile::factory()->create(['student_id' => $s->id]);
        $r = Referral::factory()->create(['student_id' => $s->id, 'case_id' => $c->id, 'status' => 'in_progress']);
        $a = $this->appt($s, $r, $c);
        $a->update(['status' => 'completed', 'checked_in' => true]);

        $this->postJson("/api/referrals/{$r->id}/feedback", ['feedback_notes' => 'Went well', 'feedback_checklist' => []])->assertStatus(422);
        $this->postJson("/api/referrals/{$r->id}/feedback", ['feedback_notes' => 'Went well'])->assertStatus(422);
        $this->postJson("/api/referrals/{$r->id}/feedback", ['feedback_notes' => 'Went well', 'feedback_checklist' => ['others']])->assertStatus(422);
        $this->assertSame(0, $r->feedbackSlips()->count());

        $this->postJson("/api/referrals/{$r->id}/feedback", ['feedback_notes' => 'Went well', 'feedback_checklist' => ['counseling']])->assertOk();
        $this->assertSame(1, $r->feedbackSlips()->count());
    }

    public function test_case_cannot_be_closed_without_session_notes(): void
    {
        $this->staff();
        $s = Student::factory()->create();
        $c = CaseFile::factory()->create(['student_id' => $s->id, 'status' => 'open']);
        $body = ['interventions_applied' => 'x', 'outcomes' => 'y', 'closure_summary' => 'z'];

        $this->postJson("/api/cases/{$c->id}/close", $body)->assertStatus(422);
        $this->assertNotSame('closed', $c->fresh()->status);

        SessionNote::create([
            'case_id' => $c->id, 'student_id' => $s->id, 'recorded_by_user_id' => auth()->id() ?? User::first()->id,
            'session_number' => 1, 'session_date' => now()->toDateString(), 'session_type' => 'initial', 'observations' => 'o', 'interventions' => 'i',
        ]);
        $this->postJson("/api/cases/{$c->id}/close", $body)->assertOk();
        $this->assertSame('closed', $c->fresh()->status);
    }
}