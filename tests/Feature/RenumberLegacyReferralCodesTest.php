<?php

namespace Tests\Feature;

use App\Models\Referral;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RenumberLegacyReferralCodesTest extends TestCase
{
    use RefreshDatabase;

    // Simulates a referral stuck on the old "YY-T-####" scheme, as if it
    // had been created back when a Ctrl No. was configured for QF-OSS-01.
    // A raw query-builder update, not ->save(): the factory-created
    // instance's in-memory referral_code is already the new REF-… value
    // Referral::booted() assigned, so a save() here would see no change
    // and silently no-op (same trap as the sif_client_no reset in
    // StudentProfileManagementTest).
    private function makeLegacy(string $code, ?Carbon $createdAt = null): Referral
    {
        $referral = Referral::factory()->create();
        DB::table('referrals')->where('id', $referral->id)->update([
            'referral_code' => $code,
            'created_at'    => $createdAt ?? $referral->created_at,
        ]);
        return $referral->fresh();
    }

    public function test_dry_run_lists_changes_without_saving_anything(): void
    {
        $legacy = $this->makeLegacy('26-1-0005', Carbon::parse('2026-03-01'));

        $this->artisan('referrals:renumber-legacy-codes --dry-run')
            ->expectsOutputToContain('would be renumbered')
            ->assertExitCode(0);

        $this->assertSame('26-1-0005', $legacy->fresh()->referral_code);
    }

    public function test_renumbers_legacy_codes_in_chronological_order_per_year(): void
    {
        $older = $this->makeLegacy('26-1-0005', Carbon::parse('2026-03-01 08:00:00'));
        $newer = $this->makeLegacy('26-2-0001', Carbon::parse('2026-03-01 09:00:00'));

        $this->artisan('referrals:renumber-legacy-codes')
            ->expectsConfirmation(
                '2 referral(s) will be renumbered. This updates the referral_code column directly and cannot be undone automatically - continue?',
                'yes'
            )
            ->assertExitCode(0);

        $this->assertSame('REF-2026-0001', $older->fresh()->referral_code);
        $this->assertSame('REF-2026-0002', $newer->fresh()->referral_code);
    }

    public function test_already_correct_codes_are_left_alone_and_counted_against_collisions(): void
    {
        // Already on the new scheme for 2026 - the legacy one below must
        // not be assigned 0001 and collide with this.
        $alreadyCorrect = $this->makeLegacy('REF-2026-0001', Carbon::parse('2026-01-01'));
        $legacy = $this->makeLegacy('26-1-0009', Carbon::parse('2026-02-01'));

        $this->artisan('referrals:renumber-legacy-codes')
            ->expectsConfirmation(
                '1 referral(s) will be renumbered. This updates the referral_code column directly and cannot be undone automatically - continue?',
                'yes'
            )
            ->assertExitCode(0);

        $this->assertSame('REF-2026-0001', $alreadyCorrect->fresh()->referral_code);
        $this->assertSame('REF-2026-0002', $legacy->fresh()->referral_code);
    }

    public function test_declining_the_confirmation_changes_nothing(): void
    {
        $legacy = $this->makeLegacy('26-1-0005', Carbon::parse('2026-03-01'));

        $this->artisan('referrals:renumber-legacy-codes')
            ->expectsConfirmation(
                '1 referral(s) will be renumbered. This updates the referral_code column directly and cannot be undone automatically - continue?',
                'no'
            )
            ->assertExitCode(0);

        $this->assertSame('26-1-0005', $legacy->fresh()->referral_code);
    }

    public function test_nothing_to_do_when_everything_is_already_on_the_new_scheme(): void
    {
        Referral::factory()->create(); // gets a REF-{year}-#### code automatically

        $this->artisan('referrals:renumber-legacy-codes')
            ->expectsOutputToContain('Nothing to do')
            ->assertExitCode(0);
    }
}