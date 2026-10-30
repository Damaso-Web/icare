<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\CaseFile;
use App\Models\Referral;
use Illuminate\Http\Request;

class MonitoringController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if (!in_array($user->role, ['admin', 'gcu_staff', 'sdu_head', 'tmdu_staff', 'tmdu_head'])) {
            abort(403, 'Unauthorized.');
        }

        // Monitoring is a unit-specific work queue. Keep the same unit
        // boundaries as the underlying case, appointment, and referral lists.
        $unitFilter = function ($query, $column = 'unit') use ($user) {
            if ($user->isTMDUStaff()) {
                $query->where($column, 'TMDU');
            } elseif ($user->isSDUHead()) {
                $query->where($column, 'SDU');
            } else {
                $query->where($column, 'GCU');
            }
            return $query;
        };

        // Feature 60: pending referrals exclude complaint-derived records and
        // apply the same TMDU ownership boundary as ReferralController::index.
        $pendingReferralsQuery = Referral::with(['student', 'referredBy'])
            ->where('status', 'submitted')
            ->whereNull('complaint_id');
        if ($user->isTMDUStaff()) {
            $pendingReferralsQuery->tmduOwned();
        } else {
            $pendingReferralsQuery->notTmduOwned();
        }
        if ($user->isSDUHead()) {
            $pendingReferralsQuery->where('referred_by_user_id', $user->id);
        }

        // Feature 61: pending appointments, scoped to the user's service unit.
        $pendingAppointmentsQuery = Appointment::with(['student', 'staff'])
            ->where('status', 'pending');
        $unitFilter($pendingAppointmentsQuery);

        // Feature 62: unresolved cases and follow-up flags, scoped to the same
        // unit so summary cards cannot reveal counts from another service.
        $unresolvedCasesQuery = CaseFile::with(['student', 'counselor', 'latestReferral'])
            ->where(function ($q) {
                $q->whereIn('status', ['open', 'in_progress', 'awaiting_testing', 'awaiting_external', 'on_hold'])
                  ->orWhere('requires_follow_up', true);
            });
        $unitFilter($unresolvedCasesQuery, 'current_unit');

        $flaggedFollowUpQuery = CaseFile::where('requires_follow_up', true);
        $unitFilter($flaggedFollowUpQuery, 'current_unit');

        // Compute totals before paginate() mutates the builders with limit and
        // offset, otherwise the stats can accidentally reflect only one page.
        $stats = [
            'pending_referrals'    => (clone $pendingReferralsQuery)->count(),
            'pending_appointments' => (clone $pendingAppointmentsQuery)->count(),
            'unresolved_cases'     => (clone $unresolvedCasesQuery)->count(),
            'flagged_follow_up'    => (clone $flaggedFollowUpQuery)->count(),
        ];

        $pendingReferrals = $pendingReferralsQuery->latest()->paginate(10, ['*'], 'referrals_page');
        $pendingAppointments = $pendingAppointmentsQuery->orderBy('appointment_date')->paginate(10, ['*'], 'appointments_page');
        $unresolvedCases = $unresolvedCasesQuery->latest()->paginate(10, ['*'], 'cases_page');

        return response()->json([
            'stats'                => $stats,
            'pending_referrals'    => $pendingReferrals,
            'pending_appointments' => $pendingAppointments,
            'unresolved_cases'     => $unresolvedCases,
        ]);
    }
}