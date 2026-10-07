<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\CaseFile;
use App\Models\Complaint;
use App\Models\Referral;
use App\Models\TestingRecord;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->isAdmin() || $user->isGCUStaff()) {
            return response()->json($this->gcuDashboard($user));
        }

        if ($user->isSDUHead()) {
            return response()->json($this->sduDashboard());
        }

        if ($user->isTMDUStaff()) {
            return response()->json($this->tmduDashboard());
        }

        if ($user->isDeanSecretary()) {
            return response()->json($this->deanSecretaryDashboard($user));
        }

        if ($user->isFaculty()) {
            return response()->json($this->facultyDashboard($user));
        }

        return response()->json(['message' => 'No dashboard available.'], 403);
    }

    /** GCU's open SIFs: same rules as the Student Information Files list. */
    private function gcuOpenCases()
    {
        return CaseFile::where('current_unit', 'GCU')
            ->whereIn('status', ['open', 'in_progress'])
            ->whereHas('student', fn($s) => $s->where('is_active', true))
            ->whereHas('referrals', fn($r) => $r->whereNotNull('acknowledged_at'));
    }

    /** GCU's referral inbox: same rules as the Referral Queue (no TMDU slips, complaints or archived). */
    private function gcuPendingReferrals()
    {
        return Referral::where('status', 'submitted')
            ->where('is_archived', false)
            ->whereNull('complaint_id')
            ->notTmduOwned();
    }

    private function gcuDashboard($user): array
    {
        return [
            'stats' => [
                // Counted per unit and with the same filters as the pages the
                // cards link to (Student Information Files / Referral Queue), so
                // the number on the card always matches the list behind it.
                'open_cases'        => $this->gcuOpenCases()->count(),
                'pending_referrals' => $this->gcuPendingReferrals()->count(),
                'appointments_today'=> Appointment::where('appointment_date', today())
                                        ->where('unit', 'GCU')
                                        ->whereNotIn('status', ['cancelled', 'rescheduled'])->where(fn($q) => $q->whereNull('request_status')->orWhereNotIn('request_status', ['awaiting_student', 'cancelled', 'rescheduled']))->count(),
            ],
            'recent_referrals' => $this->gcuPendingReferrals()
                ->with(['student', 'referredBy'])
                ->latest()
                ->take(5)
                ->get(),
            'upcoming_appointments' => Appointment::with(['student', 'staff'])
                ->where('appointment_date', '>=', today())
                ->where('unit', 'GCU')
                ->whereNotIn('status', ['cancelled', 'rescheduled'])->where(fn($q) => $q->whereNull('request_status')->orWhereNotIn('request_status', ['awaiting_student', 'cancelled', 'rescheduled']))
                ->orderBy('appointment_date')
                ->orderBy('start_time')
                ->take(5)
                ->get(),
            'my_cases' => CaseFile::with(['student', 'latestReferral'])
                ->where('primary_counselor_id', $user->id)
                ->whereIn('status', ['open', 'in_progress'])
                ->latest()
                ->take(5)
                ->get(),
        ];
    }

    private function sduDashboard(): array
    {
        return [
            'stats' => [
                'active_cases'      => CaseFile::where('current_unit', 'SDU')
                                        ->whereIn('status', ['open', 'in_progress'])
                                        ->whereHas('student', fn($s) => $s->where('is_active', true))->count(),
                'pending_complaints' => Complaint::where('status', 'pending')->count(),
                'appointments_today'=> Appointment::where('appointment_date', today())
                                        ->where('unit', 'SDU')
                                        ->whereNotIn('status', ['cancelled', 'rescheduled'])->where(fn($q) => $q->whereNull('request_status')->orWhereNotIn('request_status', ['awaiting_student', 'cancelled', 'rescheduled']))->count(),
            ],
            'recent_cases' => CaseFile::with('student')
                ->where('current_unit', 'SDU')
                ->latest()
                ->take(5)
                ->get(),
            'upcoming_appointments' => Appointment::with(['student', 'staff'])
                ->where('appointment_date', '>=', today())
                ->where('unit', 'SDU')
                ->whereNotIn('status', ['cancelled', 'rescheduled'])->where(fn($q) => $q->whereNull('request_status')->orWhereNotIn('request_status', ['awaiting_student', 'cancelled', 'rescheduled']))
                ->orderBy('appointment_date')
                ->orderBy('start_time')
                ->take(5)
                ->get(),
        ];
    }

    private function tmduDashboard(): array
    {
        return [
            'stats' => [
                'pending_testing'   => TestingRecord::where('status', 'pending')->count(),
                'in_progress'       => TestingRecord::where('status', 'in_progress')->count(),
                'completed'         => TestingRecord::where('status', 'completed')->count(),
                'appointments_today'=> Appointment::where('appointment_date', today())
                                        ->where('unit', 'TMDU')
                                        ->whereNotIn('status', ['cancelled', 'rescheduled'])->where(fn($q) => $q->whereNull('request_status')->orWhereNotIn('request_status', ['awaiting_student', 'cancelled', 'rescheduled']))->count(),
            ],
            'testing_queue' => TestingRecord::with(['student', 'referredBy'])
                ->whereIn('status', ['pending', 'scheduled'])
                ->latest()
                ->take(5)
                ->get(),
            'upcoming_appointments' => Appointment::with(['student', 'staff'])
                ->where('appointment_date', '>=', today())
                ->where('unit', 'TMDU')
                ->whereNotIn('status', ['cancelled', 'rescheduled'])->where(fn($q) => $q->whereNull('request_status')->orWhereNotIn('request_status', ['awaiting_student', 'cancelled', 'rescheduled']))
                ->orderBy('appointment_date')
                ->orderBy('start_time')
                ->take(5)
                ->get(),
        ];
    }

    private function facultyDashboard($user): array
    {
        return [
            'stats' => [
                'my_referrals'      => Referral::where('referred_by_user_id', $user->id)->count(),
                'pending'           => Referral::where('referred_by_user_id', $user->id)
                                        ->where('status', 'submitted')->count(),
                'acknowledged'      => Referral::where('referred_by_user_id', $user->id)
                                        ->where('status', 'acknowledged')->count(),
                'completed'         => Referral::where('referred_by_user_id', $user->id)
                                        ->whereIn('status', ['completed', 'closed'])->count(),
            ],
            'recent_referrals' => Referral::with('student')
                ->where('referred_by_user_id', $user->id)
                ->latest()
                ->take(5)
                ->get(),
        ];
    }

    private function deanSecretaryDashboard($user): array
    {
        $collegeReferrals = Referral::whereHas('student', fn($s) => $s->where('college', $user->college));

        return [
            'stats' => [
                'my_referrals' => (clone $collegeReferrals)->count(),
                'pending'      => (clone $collegeReferrals)->where('status', 'submitted')->count(),
                'acknowledged' => (clone $collegeReferrals)->where('status', 'acknowledged')->count(),
                'completed'    => (clone $collegeReferrals)->whereIn('status', ['completed', 'closed'])->count(),
            ],
            'recent_referrals' => (clone $collegeReferrals)->with('student')
                ->latest()
                ->take(5)
                ->get(),
        ];
    }
}