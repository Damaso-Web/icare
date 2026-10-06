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
use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ReportController extends Controller
{
    public function referrals(Request $request)
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to'   => 'nullable|date',
        ]);

        return response()->json($this->buildReferralsReport($request));
    }

    public function appointments(Request $request)
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to'   => 'nullable|date',
        ]);

        return response()->json($this->buildAppointmentsReport($request));
    }

    public function cases(Request $request)
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to'   => 'nullable|date',
        ]);

        return response()->json($this->buildCasesReport($request));
    }

    public function recurringConcerns(Request $request)
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to'   => 'nullable|date',
        ]);

        return response()->json($this->buildRecurringReport($request));
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

    // ---------- Shared report builders (used by both the JSON endpoints above and the exports below) ----------

    private function buildReferralsReport(Request $request): array
    {
        $query = Referral::query()
            ->when($request->date_from, fn($q) => $q->whereDate('created_at', '>=', $request->date_from))
            ->when($request->date_to,   fn($q) => $q->whereDate('created_at', '<=', $request->date_to));

        return [
            'total'           => $query->count(),
            'by_status'       => $query->clone()->groupBy('status')
                                    ->select('status', DB::raw('count(*) as count'))
                                    ->get()->toArray(),
            'by_type'         => $query->clone()->groupBy('referral_type')
                                    ->select('referral_type', DB::raw('count(*) as count'))
                                    ->get()->toArray(),
            'by_college'      => $query->clone()->groupBy('referrer_college')
                                    ->select('referrer_college', DB::raw('count(*) as count'))
                                    ->get()->toArray(),
            'monthly_trend'   => $query->clone()
                                    ->select(
                                        DB::raw('MONTH(created_at) as month'),
                                        DB::raw('YEAR(created_at) as year'),
                                        DB::raw('count(*) as count')
                                    )
                                    ->groupBy('year', 'month')
                                    ->orderBy('year')
                                    ->orderBy('month')
                                    ->get()->toArray(),
        ];
    }

    private function buildAppointmentsReport(Request $request): array
    {
        $query = Appointment::query()
            ->when($request->date_from, fn($q) => $q->whereDate('appointment_date', '>=', $request->date_from))
            ->when($request->date_to,   fn($q) => $q->whereDate('appointment_date', '<=', $request->date_to));

        return [
            'total'          => $query->count(),
            'by_status'      => $query->clone()->groupBy('status')
                                    ->select('status', DB::raw('count(*) as count'))
                                    ->get()->toArray(),
            'by_unit'        => $query->clone()->groupBy('unit')
                                    ->select('unit', DB::raw('count(*) as count'))
                                    ->get()->toArray(),
            // Status counts per unit - the Appointment Summary table needs
            // each unit's own breakdown, not the all-unit by_status totals.
            'by_unit_status' => $query->clone()->groupBy('unit', 'status')
                                    ->select('unit', 'status', DB::raw('count(*) as count'))
                                    ->get()->toArray(),
            'by_type'        => $query->clone()->groupBy('appointment_type')
                                    ->select('appointment_type', DB::raw('count(*) as count'))
                                    ->get()->toArray(),
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
                                    ->get()->toArray(),
        ];
    }

    private function buildCasesReport(Request $request): array
    {
        $query = CaseFile::query()
            ->when($request->date_from, fn($q) => $q->whereDate('opened_date', '>=', $request->date_from))
            ->when($request->date_to,   fn($q) => $q->whereDate('opened_date', '<=', $request->date_to));

        return [
            'total'          => $query->count(),
            'pending'        => $query->clone()->whereNotIn('status', ['resolved', 'closed'])->count(),
            'by_status'      => $query->clone()->groupBy('status')
                                    ->select('status', DB::raw('count(*) as count'))
                                    ->get()->toArray(),
            'by_unit'        => $query->clone()->groupBy('current_unit')
                                    ->select('current_unit', DB::raw('count(*) as count'))
                                    ->get()->toArray(),
            'by_type'        => $query->clone()->groupBy('case_type')
                                    ->select('case_type', DB::raw('count(*) as count'))
                                    ->get()->toArray(),
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
                                    ->get()->toArray(),
        ];
    }

    private function buildRecurringReport(Request $request): array
    {
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

        return [
            'by_type'                  => $byType->toArray(),
            'total_recurring_students' => Student::withCount('referrals')->having('referrals_count', '>', 1)->count(),
            'top_recurring_students'   => $topRecurringStudents->toArray(),
        ];
    }

    // ---------- Exports (B284) ----------
    // Both exports cover the same Referrals / Cases / Appointments / Recurring
    // Concerns summary shown on the Reports & Analytics page, for the same
    // date range the user has set there.

    public function exportPdf(Request $request)
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to'   => 'nullable|date',
        ]);

        $data = [
            'generated_at' => now()->format('F j, Y g:i A'),
            'date_from'    => $request->date_from,
            'date_to'      => $request->date_to,
            'referrals'    => $this->buildReferralsReport($request),
            'cases'        => $this->buildCasesReport($request),
            'appointments' => $this->buildAppointmentsReport($request),
            'recurring'    => $this->buildRecurringReport($request),
        ];

        $pdf = Pdf::loadView('reports.export-pdf', $data)->setPaper('a4', 'portrait');

        return $pdf->download('iCARE-Report-' . now()->format('Y-m-d') . '.pdf');
    }

    public function exportExcel(Request $request)
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to'   => 'nullable|date',
        ]);

        $referrals    = $this->buildReferralsReport($request);
        $cases        = $this->buildCasesReport($request);
        $appointments = $this->buildAppointmentsReport($request);
        $recurring    = $this->buildRecurringReport($request);

        $spreadsheet = new Spreadsheet();

        // ----- Summary sheet -----
        $summary = $spreadsheet->getActiveSheet();
        $summary->setTitle('Summary');
        $summary->fromArray([
            ['iCARE Reports & Analytics'],
            ['Generated: ' . now()->format('F j, Y g:i A')],
            ['Date Range: ' . ($request->date_from ?: 'All time') . ' to ' . ($request->date_to ?: 'present')],
            [],
            ['Metric', 'Value'],
            ['Total Referrals', $referrals['total']],
            ['Total Cases', $cases['total']],
            ['Pending Cases', $cases['pending']],
            ['Avg. Days to Close', $cases['avg_days_to_close']],
            ['Total Appointments', $appointments['total']],
            ['TMDU Assessments', $cases['referred_tmdu']],
            ['Students with Recurring Referrals', $recurring['total_recurring_students']],
        ], null, 'A1');

        // ----- Referrals sheet -----
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Referrals');
        $sheet->fromArray(['By Status'], null, 'A1');
        $sheet->fromArray(['Status', 'Count'], null, 'A2');
        $this->writeRows($sheet, $referrals['by_status'], ['status', 'count'], 3);

        $row = 3 + count($referrals['by_status']) + 2;
        $sheet->setCellValue("A{$row}", 'By Type');
        $sheet->fromArray(['Referral Type', 'Count'], null, 'A' . ($row + 1));
        $this->writeRows($sheet, $referrals['by_type'], ['referral_type', 'count'], $row + 2);

        // ----- Cases sheet -----
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Cases');
        $sheet->fromArray(['By Status'], null, 'A1');
        $sheet->fromArray(['Status', 'Count'], null, 'A2');
        $this->writeRows($sheet, $cases['by_status'], ['status', 'count'], 3);

        $row = 3 + count($cases['by_status']) + 2;
        $sheet->setCellValue("A{$row}", 'By Unit');
        $sheet->fromArray(['Unit', 'Count'], null, 'A' . ($row + 1));
        $this->writeRows($sheet, $cases['by_unit'], ['current_unit', 'count'], $row + 2);

        // ----- Appointments sheet -----
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Appointments');
        $sheet->fromArray(['By Unit'], null, 'A1');
        $sheet->fromArray(['Unit', 'Count'], null, 'A2');
        $this->writeRows($sheet, $appointments['by_unit'], ['unit', 'count'], 3);

        $row = 3 + count($appointments['by_unit']) + 2;
        $sheet->setCellValue("A{$row}", 'By Status');
        $sheet->fromArray(['Status', 'Count'], null, 'A' . ($row + 1));
        $this->writeRows($sheet, $appointments['by_status'], ['status', 'count'], $row + 2);

        // ----- Recurring Concerns sheet -----
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Recurring Concerns');
        $sheet->fromArray(['Referral Type', 'Total Referrals', 'Distinct Students', 'Recurring Students'], null, 'A1');
        $this->writeRows($sheet, $recurring['by_type'], ['referral_type', 'total_referrals', 'distinct_students', 'recurring_students'], 2);

        $spreadsheet->setActiveSheetIndex(0);

        $filename = 'iCARE-Report-' . now()->format('Y-m-d') . '.xlsx';
        $tmpPath = storage_path('app/' . $filename);
        (new Xlsx($spreadsheet))->save($tmpPath);

        return response()->download($tmpPath, $filename)->deleteFileAfterSend(true);
    }

    /**
     * Writes an array of associative rows into $sheet starting at $startRow,
     * pulling the given $keys from each row in order.
     */
    private function writeRows($sheet, array $rows, array $keys, int $startRow): void
    {
        $r = $startRow;
        foreach ($rows as $row) {
            $col = 'A';
            foreach ($keys as $key) {
                $sheet->setCellValue("{$col}{$r}", $row[$key] ?? '');
                $col++;
            }
            $r++;
        }
    }
}