<?php

namespace Tests\Feature;

use App\Models\Referral;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

// B#, 11/4/2026: the Referral Queue ("Referrals") list used to page at 20;
// now matches Students' own page size of 10.
class ReferralQueuePaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_referral_queue_pages_at_ten(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        Referral::factory()->count(15)->create();

        $res = $this->getJson('/api/referrals')->assertOk();
        $this->assertCount(10, $res->json('data'));
        $this->assertSame(10, $res->json('per_page'));
        $this->assertSame(2, $res->json('last_page'));
    }
}