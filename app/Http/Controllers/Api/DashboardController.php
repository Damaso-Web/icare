<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\CaseFile;
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

        if ($user->isFaculty() || $user->isDeanSecretary()) {
            return response()->json($this->facultyDashboard($user));
        }

        return response()->json(['message' => 'No dashboard available.'], 403);
    }

    private function gcuDashboard($user): array
    {
        return [
            'stats' => [
                'open_cases'        => CaseFile::whereIn('status', ['open', 'in_progress'])->count(),
                'pending_referrals' => Referral::where('status', 'submitted')->count(),
                'high_priority'     => Referral::where('urgency_level', 'high')
                                        ->whereNotIn('status', ['completed', 'closed'])->count(),
                'appointments_today'=> Appointment::where('appointment_date', today())
                                        ->where('unit', 'GCU')
                                        ->whereNotIn('status', ['cancelled'])->count(),
            ],
            'recent_referrals' => Referral::with(['student', 'referredBy'])
                ->where('status', 'submitted')
                ->latest()
                ->take(5)
                ->get(),
            'upcoming_appointments' => Appointment::with(['student', 'staff'])
                ->where('appointment_date', '>=', today())
                ->where('unit', 'GCU')
                ->whereNotIn('status', ['cancelled'])
                ->orderBy('appointment_date')
                ->orderBy('start_time')
                ->take(5)
                ->get(),
            'my_cases' => CaseFile::with('student')
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
                                        ->whereIn('status', ['open', 'in_progress'])->count(),
                'pending_referrals' => Referral::where('referral_type', 'disciplinary')
                                        ->where('status', 'submitted')->count(),
                'appointments_today'=> Appointment::where('appointment_date', today())
                                        ->where('unit', 'SDU')
                                        ->whereNotIn('status', ['cancelled'])->count(),
            ],
            'recent_cases' => CaseFile::with('student')
                ->where('current_unit', 'SDU')
                ->latest()
                ->take(5)
                ->get(),
            'upcoming_appointments' => Appointment::with(['student', 'staff'])
                ->where('appointment_date', '>=', today())
                ->where('unit', 'SDU')
                ->whereNotIn('status', ['cancelled'])
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
                                        ->whereNotIn('status', ['cancelled'])->count(),
            ],
            'testing_queue' => TestingRecord::with(['student', 'referredBy'])
                ->whereIn('status', ['pending', 'scheduled'])
                ->latest()
                ->take(5)
                ->get(),
            'upcoming_appointments' => Appointment::with(['student', 'staff'])
                ->where('appointment_date', '>=', today())
                ->where('unit', 'TMDU')
                ->whereNotIn('status', ['cancelled'])
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
}