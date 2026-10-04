<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Referral;

// B275: 'in_review' is retired from the referral status pipeline (see
// Referral::STATUS_ORDER / canMoveStatusTo()). This one-time data migration
// moves any referral still sitting at 'in_review' forward onto the new
// pipeline, based on what's actually true about it right now:
//   - an attended appointment (status 'completed')      -> 'in_progress'
//   - a confirmed-but-not-yet-attended appointment       -> 'scheduled'
//   - neither                                            -> 'acknowledged'
// This never moves a referral backward - every one of these targets is at
// or ahead of 'in_review' on the old pipeline, and matches exactly what
// AppointmentController::confirm()/checkIn() would have set going forward.
// The 'in_review' value is left in the referrals.status enum definition
// itself (no ALTER TABLE) since no code writes it anymore and narrowing the
// enum isn't needed for the fix to work.
return new class extends Migration
{
    public function up(): void
    {
        Referral::where('status', 'in_review')->get()->each(function (Referral $referral) {
            $hasAttended = $referral->case
                ? $referral->case->hasAttendedAppointment()
                : $referral->appointments()->where('status', 'completed')->exists();

            if ($hasAttended) {
                $referral->update(['status' => 'in_progress']);
                return;
            }

            $hasConfirmedAppointment = ($referral->case
                ? $referral->case->appointments()
                : $referral->appointments())
                ->where('request_status', 'confirmed')
                ->exists();

            $referral->update(['status' => $hasConfirmedAppointment ? 'scheduled' : 'acknowledged']);
        });
    }

    public function down(): void
    {
        // Not reversible - the original 'in_review' rows can't be
        // distinguished from referrals that genuinely reached 'acknowledged'/
        // 'scheduled'/'in_progress' on their own afterward.
    }
};