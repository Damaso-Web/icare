<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Appointment;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function referrals(Request $request)
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to'   => 'nullable|date',
        ]);

        $query = Referral::query()
            ->when($request->date_from, fn($q) => $q->whereDate('created_at', '>=', $request->date_from))
            ->when($request->date_to,   fn($q) => $q->whereDate('created_at', '<=', $request->date_to));

        return response()->json([
            'total'           => $query->count(),
            'by_status'       => $query->clone()->groupBy('status')
                                    ->select('status', DB::raw('count(*) as count'))
                                    ->get(),
            'by_type'         => $query->clone()->groupBy('referral_type')
                                    ->select('referral_type', DB::raw('count(*) as count'))
                                    ->get(),
            'by_urgency'      => $query->clone()->groupBy('urgency_level')
                                    ->select('urgency_level', DB::raw('count(*) as count'))
                                    ->get(),
            'by_college'      => $query->clone()->groupBy('referrer_college')
                                    ->select('referrer_college', DB::raw('count(*) as count'))
                                    ->get(),
            'monthly_trend'   => $query->clone()
                                    ->select(
                                        DB::raw('MONTH(created_at) as month'),
                                        DB::raw('YEAR(created_at) as year'),
                                        DB::raw('count(*) as count')
                                    )
                                    ->groupBy('year', 'month')
                                    ->orderBy('year')
                                    ->orderBy('month')
                                    ->get(),
        ]);
    }

    public function appointments(Request $request)
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to'   => 'nullable|date',
        ]);

        $query = Appointment::query()
            ->when($request->date_from, fn($q) => $q->whereDate('appointment_date', '>=', $request->date_from))
            ->when($request->date_to,   fn($q) => $q->whereDate('appointment_date', '<=', $request->date_to));

        return response()->json([
            'total'          => $query->count(),
            'by_status'      => $query->clone()->groupBy('status')
                                    ->select('status', DB::raw('count(*) as count'))
                                    ->get(),
            'by_unit'        => $query->clone()->groupBy('unit')
                                    ->select('unit', DB::raw('count(*) as count'))
                                    ->get(),
            'by_type'        => $query->clone()->groupBy('appointment_type')
                                    ->select('appointment_type', DB::raw('count(*) as count'))
                                    ->get(),
            'no_show_rate'   => $query->clone()->where('status', 'no_show')->count(),
            'monthly_trend'  => $query->clone()
                                    ->select(
                                        DB::raw('MONTH(appointment_date) as month'),
                                        DB::raw('YEAR(appointment_date) as year'),
                                        DB::raw('count(*) as count')
                                    )
                                    ->groupBy('year', 'month')
                                    ->orderBy('year')
                                    ->orderBy('month')
                                    ->get(),
        ]);
    }

    public function cases(Request $request)
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to'   => 'nullable|date',
        ]);

        $query = CaseFile::query()
            ->when($request->date_from, fn($q) => $q->whereDate('opened_date', '>=', $request->date_from))
            ->when($request->date_to,   fn($q) => $q->whereDate('opened_date', '<=', $request->date_to));

        return response()->json([
            'total'          => $query->count(),
            'pending'        => $query->clone()->whereNotIn('status', ['resolved', 'closed'])->count(),
            'by_status'      => $query->clone()->groupBy('status')
                                    ->select('status', DB::raw('count(*) as count'))
                                    ->get(),
            'by_unit'        => $query->clone()->groupBy('current_unit')
                                    ->select('current_unit', DB::raw('count(*) as count'))
                                    ->get(),
            'by_type'        => $query->clone()->groupBy('case_type')
                                    ->select('case_type', DB::raw('count(*) as count'))
                                    ->get(),
            'recurring'      => $query->clone()->where('is_recurring', true)->count(),
            'referred_tmdu'  => $query->clone()->where('referred_to_tmdu', true)->count(),
            'avg_days_to_close' => round(
                (clone $query)->where('status', 'closed')->whereNotNull('closed_date')
                    ->avg(DB::raw('DATEDIFF(closed_date, opened_date)')) ?? 0,
                1
            ),
            'monthly_trend'  => $query->clone()
                                    ->select(
                                        DB::raw('MONTH(opened_date) as month'),
                                        DB::raw('YEAR(opened_date) as year'),
                                        DB::raw('count(*) as count')
                                    )
                                    ->groupBy('year', 'month')
                                    ->orderBy('year')
                                    ->orderBy('month')
                                    ->get(),
        ]);
    }

    public function recurringConcerns(Request $request)
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to'   => 'nullable|date',
        ]);

        $query = Referral::query()
            ->when($request->date_from, fn($q) => $q->whereDate('created_at', '>=', $request->date_from))
            ->when($request->date_to,   fn($q) => $q->whereDate('created_at', '<=', $request->date_to));

        $byType = $query->clone()
            ->select('referral_type', 'student_id')
            ->get()
            ->groupBy('referral_type')
            ->map(function ($referrals, $type) {
                $byStudent = $referrals->groupBy('student_id');
                return [
                    'referral_type'      => $type,
                    'total_referrals'    => $referrals->count(),
                    'distinct_students'  => $byStudent->count(),
                    'recurring_students' => $byStudent->filter(fn($g) => $g->count() > 1)->count(),
                ];
            })
            ->sortByDesc('recurring_students')
            ->values();

        $topRecurringStudents = Student::withCount('referrals')
            ->having('referrals_count', '>', 1)
            ->orderByDesc('referrals_count')
            ->limit(10)
            ->get(['id', 'student_id', 'first_name', 'last_name', 'college']);

        return response()->json([
            'by_type'                  => $byType,
            'total_recurring_students' => Student::withCount('referrals')->having('referrals_count', '>', 1)->count(),
            'top_recurring_students'   => $topRecurringStudents,
        ]);
    }

    public function dashboardStats()
    {
        return response()->json([
            'total_students'    => Student::count(),
            'total_referrals'   => Referral::count(),
            'total_cases'       => CaseFile::count(),
            'open_cases'        => CaseFile::whereIn('status', ['open', 'in_progress'])->count(),
            'closed_cases'      => CaseFile::where('status', 'closed')->count(),
            'total_appointments'=> Appointment::count(),
        ]);
    }
}