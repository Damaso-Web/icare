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

// Panel fix (11/8/2026, items #6/#7): the Admission Slip (QF-OSS-GCU-09) can
// now carry several dates absent/tardy, each its own Whole Day/Half Day
// entry, with the number of days absent/tardy counted automatically from
// them. The slip still has ONE Excused/Unexcused verdict covering every
// date on it. See ReferralController::saveAdmissionSlip().
class AdmissionSlipDatesTest extends TestCase
{
    use RefreshDatabase;

    private function unlockedReferral(): Referral
    {
        $student = Student::factory()->create();
        $case = CaseFile::factory()->create(['student_id' => $student->id]);
        $referral = Referral::factory()->create([
            'student_id' => $student->id, 'case_id' => $case->id,
            'referral_type' => 'class_attendance', 'status' => 'acknowledged',
            'acknowledged_at' => now(),
        ]);
        Appointment::create([
            'referral_id' => $referral->id, 'case_id' => $case->id, 'student_id' => $student->id,
            'unit' => 'GCU', 'appointment_type' => 'counseling', 'appointment_date' => today(),
            'start_time' => '09:00', 'end_time' => '10:00', 'status' => 'completed',
        ]);
        return $referral->fresh();
    }

    private function gcuStaff(): User
    {
        return User::factory()->create(['role' => 'gcu_staff', 'is_active' => true]);
    }

    private function basePayload(array $dates, bool $excused = true): array
    {
        return [
            'admission_dates'   => $dates,
            'admission_excused' => $excused,
            'admission_details' => ['causes' => ['health'], 'actions' => ['counseling']],
        ];
    }

    public function test_a_slip_can_be_issued_with_several_whole_and_half_day_dates(): void
    {
        Sanctum::actingAs($this->gcuStaff());
        $referral = $this->unlockedReferral();

        $res = $this->postJson("/api/referrals/{$referral->id}/admission-slip", $this->basePayload([
            ['date' => '2026-11-02', 'duration' => 'whole'],
            ['date' => '2026-11-03', 'duration' => 'half', 'time_in' => '08:00', 'time_out' => '12:00'],
            ['date' => '2026-11-04', 'duration' => 'whole'],
        ]))->assertOk();

        $referral = $referral->fresh();
        $this->assertEquals(true, $referral->admission_excused);
        // Earliest date still mirrored onto the legacy single-date column.
        $this->assertEquals('2026-11-02', $referral->admission_date->toDateString());

        $dates = $referral->admission_details['dates'];
        $this->assertCount(3, $dates);
        $this->assertEquals('2026-11-02', $dates[0]['date']);
        $this->assertEquals('whole', $dates[0]['duration']);
        $this->assertNull($dates[0]['time_in']);
        $this->assertEquals('2026-11-03', $dates[1]['date']);
        $this->assertEquals('half', $dates[1]['duration']);
        $this->assertEquals('08:00', substr($dates[1]['time_in'], 0, 5));

        // 1 whole + 0.5 half + 1 whole = 2.5 days, counted automatically.
        $this->assertEquals('2.5 days', $referral->admission_details['days_count']);
    }

    public function test_whole_day_times_submitted_by_mistake_are_dropped(): void
    {
        Sanctum::actingAs($this->gcuStaff());
        $referral = $this->unlockedReferral();

        $this->postJson("/api/referrals/{$referral->id}/admission-slip", $this->basePayload([
            ['date' => '2026-11-02', 'duration' => 'whole', 'time_in' => '08:00', 'time_out' => '09:00'],
        ]))->assertOk();

        $dates = $referral->fresh()->admission_details['dates'];
        $this->assertNull($dates[0]['time_in']);
        $this->assertNull($dates[0]['time_out']);
    }

    public function test_a_single_whole_day_date_counts_as_one_day(): void
    {
        Sanctum::actingAs($this->gcuStaff());
        $referral = $this->unlockedReferral();

        $this->postJson("/api/referrals/{$referral->id}/admission-slip", $this->basePayload([
            ['date' => '2026-11-02', 'duration' => 'whole'],
        ]))->assertOk();

        $this->assertEquals('1 day', $referral->fresh()->admission_details['days_count']);
    }

    public function test_rejects_a_slip_with_no_dates(): void
    {
        Sanctum::actingAs($this->gcuStaff());
        $referral = $this->unlockedReferral();

        $this->postJson("/api/referrals/{$referral->id}/admission-slip", $this->basePayload([]))
            ->assertStatus(422);
    }

    public function test_rejects_the_same_date_added_twice(): void
    {
        Sanctum::actingAs($this->gcuStaff());
        $referral = $this->unlockedReferral();

        $this->postJson("/api/referrals/{$referral->id}/admission-slip", $this->basePayload([
            ['date' => '2026-11-02', 'duration' => 'whole'],
            ['date' => '2026-11-02', 'duration' => 'half', 'time_in' => '08:00', 'time_out' => '09:00'],
        ]))->assertStatus(422);
    }

    public function test_rejects_a_half_day_end_time_before_its_start_time(): void
    {
        Sanctum::actingAs($this->gcuStaff());
        $referral = $this->unlockedReferral();

        $this->postJson("/api/referrals/{$referral->id}/admission-slip", $this->basePayload([
            ['date' => '2026-11-02', 'duration' => 'half', 'time_in' => '10:00', 'time_out' => '09:00'],
        ]))->assertStatus(422);
    }

    public function test_updating_an_issued_slip_can_replace_its_whole_date_list(): void
    {
        Sanctum::actingAs($this->gcuStaff());
        $referral = $this->unlockedReferral();

        $this->postJson("/api/referrals/{$referral->id}/admission-slip", $this->basePayload([
            ['date' => '2026-11-02', 'duration' => 'whole'],
        ]))->assertOk();

        $this->postJson("/api/referrals/{$referral->id}/admission-slip", $this->basePayload([
            ['date' => '2026-11-05', 'duration' => 'whole'],
            ['date' => '2026-11-06', 'duration' => 'half', 'time_in' => '13:00', 'time_out' => '15:00'],
        ]))->assertOk();

        $dates = $referral->fresh()->admission_details['dates'];
        $this->assertCount(2, $dates);
        $this->assertEquals('2026-11-05', $dates[0]['date']);
        $this->assertEquals('2026-11-06', $dates[1]['date']);
    }

    public function test_marking_unexcused_still_locks_the_slip_from_further_date_changes(): void
    {
        Sanctum::actingAs($this->gcuStaff());
        $referral = $this->unlockedReferral();

        $this->postJson("/api/referrals/{$referral->id}/admission-slip", $this->basePayload([
            ['date' => '2026-11-02', 'duration' => 'whole'],
        ], excused: false))->assertOk();

        $this->postJson("/api/referrals/{$referral->id}/admission-slip", $this->basePayload([
            ['date' => '2026-11-09', 'duration' => 'whole'],
        ]))->assertStatus(422);
    }
}