<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardCardsMatchListsTest extends TestCase
{
    use RefreshDatabase;

    private function caseFor(string $unit, string $status, bool $acknowledged = true): CaseFile
    {
        $s = Student::factory()->create(['is_active' => true]);
        $c = CaseFile::factory()->create(['student_id' => $s->id, 'current_unit' => $unit, 'status' => $status]);
        Referral::factory()->create([
            'student_id' => $s->id, 'case_id' => $c->id,
            'acknowledged_at' => $acknowledged ? now() : null,
        ]);
        return $c;
    }

    public function test_open_cases_card_equals_the_list_the_card_opens(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        $this->caseFor('GCU', 'open');
        $this->caseFor('GCU', 'open');
        $this->caseFor('GCU', 'in_progress');
        $this->caseFor('GCU', 'on_hold');
        $this->caseFor('GCU', 'open', false);   // not acknowledged yet: in neither
        $this->caseFor('SDU', 'open');          // other unit: not on the GCU card

        $card = $this->getJson('/api/dashboard')->assertOk()->json('stats.open_cases');
        $list = $this->getJson('/api/cases?status=open&unit=GCU')->assertOk()->json('total');

        $this->assertSame(3, $card);
        $this->assertSame($card, $list);
    }

    public function test_pending_referrals_and_appointments_today_cards_equal_their_lists(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        $s = Student::factory()->create(['is_active' => true]);
        $c = CaseFile::factory()->create(['student_id' => $s->id, 'current_unit' => 'GCU']);
        Referral::factory()->count(2)->create(['student_id' => $s->id, 'case_id' => $c->id, 'status' => 'submitted']);
        Referral::factory()->create(['student_id' => $s->id, 'case_id' => $c->id, 'status' => 'scheduled']);

        $dash = $this->getJson('/api/dashboard')->assertOk()->json();
        $this->assertSame($dash['stats']['pending_referrals'], $this->getJson('/api/referrals?status=submitted')->json('total'));

        $today = $dash['today'];
        $base = ['case_id' => $c->id, 'student_id' => $s->id, 'unit' => 'GCU', 'appointment_type' => 'counseling',
                 'appointment_date' => today(), 'start_time' => '09:00', 'end_time' => '10:00'];
        Appointment::create($base + ['status' => 'confirmed', 'request_status' => 'pending_confirmation']);
        Appointment::create($base + ['status' => 'cancelled', 'request_status' => 'pending_confirmation']);
        Appointment::create($base + ['status' => 'pending', 'request_status' => 'awaiting_student']);

        $card = $this->getJson('/api/dashboard')->json('stats.appointments_today');
        $list = $this->getJson("/api/appointments?date={$today}&active=1")->json('total');
        $this->assertSame(1, $card);
        $this->assertSame($card, $list);
    }
}