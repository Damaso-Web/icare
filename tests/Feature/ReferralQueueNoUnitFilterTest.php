<?php

namespace Tests\Feature;

use App\Models\Referral;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

// B#, 11/4/2026: the Referrals queue's GCU/TMDU unit-filter dropdown is
// removed per request. This confirms the replacement backend behaviour:
// GCU-type and TMDU-pending referrals both show with no `unit` param sent
// at all, while SDU's Acts of Misconduct (disciplinary) still never do.
class ReferralQueueNoUnitFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_gcu_and_tmdu_pending_referrals_show_together_without_a_unit_param(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $gcu  = Referral::factory()->create(['referral_type' => 'counseling']);
        $tmduPending = Referral::factory()->create(['referral_type' => 'psychological_testing']);
        $sdu  = Referral::factory()->create(['referral_type' => 'disciplinary']);

        $res = $this->getJson('/api/referrals')->assertOk();
        $ids = array_column($res->json('data'), 'id');

        $this->assertContains($gcu->id, $ids);
        $this->assertContains($tmduPending->id, $ids);
        $this->assertNotContains($sdu->id, $ids);
    }

    public function test_tmdu_staff_still_only_sees_their_own_queue_regardless_of_unit_removal(): void
    {
        $tmduStaff = User::factory()->create(['role' => 'tmdu_staff']);
        Sanctum::actingAs($tmduStaff);

        $gcu = Referral::factory()->create(['referral_type' => 'counseling']);
        $tmduPending = Referral::factory()->create(['referral_type' => 'psychological_testing']);

        $res = $this->getJson('/api/referrals')->assertOk();
        $ids = array_column($res->json('data'), 'id');

        // TMDU staff only ever see Case Referral Slips actually sent to
        // TMDU (tmduOwned() - i.e. a testingRecord exists), not every
        // psychological_testing referral still sitting with GCU.
        $this->assertNotContains($gcu->id, $ids);
        $this->assertNotContains($tmduPending->id, $ids);
    }
}