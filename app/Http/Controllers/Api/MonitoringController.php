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

        if (!in_array($user->role, ['admin', 'gcu_staff', 'sdu_head', 'tmdu_staff'])) {
            abort(403, 'Unauthorized.');
        }

        $unitFilter = function ($query, $column = 'unit') use ($user) {
            if ($user->isTMDUStaff()) {
                $query->where($column, 'TMDU');
            } elseif ($user->isSDUHead()) {
                $query->where($column, 'SDU');
            }
            return $query;
        };

        // Feature 60: Pending referrals
        $pendingReferrals = Referral::with(['student', 'referredBy'])
            ->where('status', 'submitted')
            ->latest()
            ->paginate(10, ['*'], 'referrals_page');

        // Feature 61: Pending appointments
        $pendingAppointmentsQuery = Appointment::with(['student', 'staff'])
            ->where('status', 'pending');
        $unitFilter($pendingAppointmentsQuery);
        $pendingAppointments = $pendingAppointmentsQuery->orderBy('appointment_date')
            ->paginate(10, ['*'], 'appointments_page');

        // Feature 62: Unresolved cases (open, in_progress, awaiting_testing, awaiting_external, on_hold,
        // or explicitly flagged as requiring follow-up)
        $unresolvedCasesQuery = CaseFile::with(['student', 'counselor', 'latestReferral'])
            ->where(function ($q) {
                $q->whereIn('status', ['open', 'in_progress', 'awaiting_testing', 'awaiting_external', 'on_hold'])
                  ->orWhere('requires_follow_up', true);
            });
        $unitFilter($unresolvedCasesQuery, 'current_unit');
        $unresolvedCases = $unresolvedCasesQuery->latest()
            ->paginate(10, ['*'], 'cases_page');

        // Summary counts for quick stat cards
        $stats = [
            'pending_referrals'    => Referral::where('status', 'submitted')->count(),
            'pending_appointments' => (clone $pendingAppointmentsQuery)->count(),
            'unresolved_cases'     => (clone $unresolvedCasesQuery)->count(),
            'flagged_follow_up'    => CaseFile::where('requires_follow_up', true)->count(),
        ];

        return response()->json([
            'stats'                => $stats,
            'pending_referrals'    => $pendingReferrals,
            'pending_appointments' => $pendingAppointments,
            'unresolved_cases'     => $unresolvedCases,
        ]);
    }
}