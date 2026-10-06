<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Appointment;
use App\Models\CaseFile;
use App\Models\CaseIntervention;
use App\Models\Complaint;
use App\Models\College;
use App\Models\FeedbackSlip;
use App\Models\Referral;
use App\Models\SessionNote;
use App\Models\Student;
use App\Models\TestingRecord;
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

    public function services(Request $request)
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to'   => 'nullable|date',
        ]);

        return response()->json($this->buildServicesReport($request));
    }

    /** SDU's report: complaints received, by misconduct, college and department. */
    public function complaints(Request $request)
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to'   => 'nullable|date',
        ]);
        abort_unless($this->unit($request) === 'SDU', 403, 'The complaints report belongs to SDU.');

        return response()->json($this->buildComplaintsReport($request));
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

    // ---------- Unit scoping ----------
    // Each unit has its own report. Admin (GCU Head) may open any of them;
    // unit staff always get their own unit, whatever the request asks for.

    private const UNIT_BY_ROLE = ['gcu_staff' => 'GCU', 'tmdu_staff' => 'TMDU', 'sdu_head' => 'SDU'];

    private function unit(Request $request): string
    {
        $role = $request->user()?->role;

        if (isset(self::UNIT_BY_ROLE[$role])) {
            return self::UNIT_BY_ROLE[$role];
        }

        return in_array($request->unit, ['GCU', 'TMDU', 'SDU'], true) ? $request->unit : 'GCU';
    }

    /** Referral types belong to a unit: SDU disciplinary, TMDU testing, GCU everything else. */
    private function forUnit($query, string $unit, string $column = 'referral_type')
    {
        return match ($unit) {
            'SDU'   => $query->where($column, 'disciplinary'),
            'TMDU'  => $query->where($column, 'psychological_testing'),
            default => $query->whereNotIn($column, ['disciplinary', 'psychological_testing']),
        };
    }

    private function buildReferralsReport(Request $request): array
    {
        $query = $this->forUnit(Referral::query(), $this->unit($request))
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
            'by_student_college' => $this->buildCollegeBreakdown($request),
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

    /**
     * Referrals per college of the referred student, highest first. Every
     * college in the Management list is included, even with no referrals.
     */
    private function buildCollegeBreakdown(Request $request): array
    {
        $counts = $this->forUnit(Referral::query(), $this->unit($request), 'referrals.referral_type')
            ->join('students', 'students.id', '=', 'referrals.student_id')
            ->when($request->date_from, fn($q) => $q->whereDate('referrals.created_at', '>=', $request->date_from))
            ->when($request->date_to,   fn($q) => $q->whereDate('referrals.created_at', '<=', $request->date_to))
            ->groupBy('students.college')
            ->select('students.college', DB::raw('count(*) as count'))
            ->pluck('count', 'college');

        $rows = College::orderBy('name')->pluck('name')
            ->merge($counts->keys())
            ->unique()
            ->map(fn($name) => ['college' => $name ?: 'Not specified', 'count' => (int) ($counts[$name] ?? 0)])
            ->values()
            ->all();

        usort($rows, fn($a, $b) => [$b['count'], $a['college']] <=> [$a['count'], $b['college']]);

        return $rows;
    }

    /**
     * What the office actually delivered in the period, as opposed to the
     * referrals it received.
     */
    private function buildServicesReport(Request $request): array
    {
        $between = fn($query, string $column) => $query
            ->when($request->date_from, fn($q) => $q->whereDate($column, '>=', $request->date_from))
            ->when($request->date_to,   fn($q) => $q->whereDate($column, '<=', $request->date_to));

        $completed = fn(string $unit) => $between(Appointment::where('unit', $unit)->where('status', 'completed'), 'appointment_date')->count();

        return match ($this->unit($request)) {
            'TMDU' => [
                ['service' => 'Testing referrals received from GCU',  'count' => $between(TestingRecord::query(), 'created_at')->count()],
                ['service' => 'Testing appointments completed',       'count' => $completed('TMDU')],
                ['service' => 'Psychological tests administered',     'count' => $between(TestingRecord::whereNotNull('tests_administered'), 'testing_date')->count()],
                ['service' => 'Test results issued to GCU',           'count' => $between(TestingRecord::whereNotNull('report_sent_at'), 'report_sent_at')->count()],
            ],
            'SDU' => [
                ['service' => 'Complaints / incident reports received', 'count' => $between(Complaint::query(), 'created_at')->count()],
                ['service' => 'SDU appointments completed',             'count' => $completed('SDU')],
                ['service' => 'Sanctions recorded',                     'count' => $between(CaseIntervention::where('type', 'sanction'), 'created_at')->count()],
            ],
            default => [
                ['service' => 'Counseling sessions conducted',         'count' => $between(SessionNote::query(), 'session_date')->count()],
                ['service' => 'Counseling appointments completed',     'count' => $completed('GCU')],
                ['service' => 'Class admission slips issued',          'count' => $between(Referral::whereNotNull('admission_issued_at'), 'admission_issued_at')->count()],
                ['service' => 'Feedback slips sent to referrers',      'count' => $between(FeedbackSlip::query(), 'sent_at')->count()],
                ['service' => 'Students referred to TMDU for testing', 'count' => $between(TestingRecord::query(), 'created_at')->count()],
            ],
        };
    }

    private function buildComplaintsReport(Request $request): array
    {
        $query = Complaint::query()
            ->when($request->date_from, fn($q) => $q->whereDate('created_at', '>=', $request->date_from))
            ->when($request->date_to,   fn($q) => $q->whereDate('created_at', '<=', $request->date_to));

        // Highest count first, then alphabetical.
        $ranked = function (array $rows) {
            usort($rows, fn($a, $b) => [$b['count'], $a['label']] <=> [$a['count'], $b['label']]);
            return $rows;
        };

        $byCollege = $query->clone()->groupBy('complainee_college')
            ->select('complainee_college', DB::raw('count(*) as count'))
            ->pluck('count', 'complainee_college');

        // Every college in the Management list is shown, even with none.
        $colleges = College::orderBy('name')->pluck('name')
            ->merge($byCollege->keys())
            ->unique()
            ->map(fn($name) => ['label' => $name ?: 'Not specified', 'count' => (int) ($byCollege[$name] ?? 0)])
            ->values()->all();

        $statusLabels = ['pending' => 'Pending', 'under_review' => 'Under Review', 'resolved' => 'Resolved', 'dismissed' => 'Dismissed'];
        $byStatus = $query->clone()->groupBy('status')->select('status', DB::raw('count(*) as count'))->pluck('count', 'status');

        return [
            'total'         => $query->count(),
            'by_status'     => collect($statusLabels)
                ->map(fn($label, $key) => ['status' => $key, 'label' => $label, 'count' => (int) ($byStatus[$key] ?? 0)])
                ->filter(fn($row) => $row['count'] > 0 || in_array($row['status'], ['pending', 'under_review', 'resolved'], true))
                ->values()->all(),
            'by_misconduct' => $ranked($query->clone()->groupBy('violation_type')
                ->select('violation_type', DB::raw('count(*) as count'))->get()
                ->map(fn($r) => ['label' => $r->violation_type ?: 'Not specified', 'count' => (int) $r->count])->all()),
            'by_college'    => $ranked($colleges),
            'by_department' => $ranked($query->clone()->groupBy('complainee_department', 'complainee_college')
                ->select('complainee_department', 'complainee_college', DB::raw('count(*) as count'))->get()
                ->map(fn($r) => ['label' => $r->complainee_department ?: 'Not specified', 'college' => $r->complainee_college ?: '-', 'count' => (int) $r->count])->all()),
        ];
    }

    private function buildAppointmentsReport(Request $request): array
    {
        $query = Appointment::where('unit', $this->unit($request))
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
        $query = CaseFile::where('current_unit', $this->unit($request))
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
            // Counted wherever the case sits now - a case sent for testing has left GCU.
            'referred_tmdu'  => CaseFile::where('referred_to_tmdu', true)
                                    ->when($request->date_from, fn($q) => $q->whereDate('opened_date', '>=', $request->date_from))
                                    ->when($request->date_to,   fn($q) => $q->whereDate('opened_date', '<=', $request->date_to))
                                    ->count(),
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
        $query = $this->forUnit(Referral::query(), $this->unit($request))
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

        // Count only referrals inside the selected period, so the recurring
        // lists agree with the rest of the report for a semester or year.
        $inPeriod = ['referrals' => fn($q) => $this->forUnit($q, $this->unit($request))
            ->when($request->date_from, fn($w) => $w->whereDate('created_at', '>=', $request->date_from))
            ->when($request->date_to,   fn($w) => $w->whereDate('created_at', '<=', $request->date_to))];

        $topRecurringStudents = Student::withCount($inPeriod)
            ->having('referrals_count', '>', 1)
            ->orderByDesc('referrals_count')
            ->limit(10)
            ->get(['id', 'student_id', 'first_name', 'last_name', 'college']);

        return [
            'by_type'                  => $byType->toArray(),
            'total_recurring_students' => Student::withCount($inPeriod)->having('referrals_count', '>', 1)->get(['id'])->count(),
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
            'period_label' => 'nullable|string|max:100',
        ]);

        if ($this->unit($request) === 'SDU') {
            return Pdf::loadView('reports.export-sdu-pdf', [
                'generated_at' => now()->format('F j, Y g:i A'),
                'date_from'    => $request->date_from,
                'date_to'      => $request->date_to,
                'period_label' => $request->period_label,
                'complaints'   => $this->buildComplaintsReport($request),
            ])->setPaper('a4', 'portrait')->download('iCARE-SDU-Report-' . now()->format('Y-m-d') . '.pdf');
        }

        $data = [
            'generated_at' => now()->format('F j, Y g:i A'),
            'unit'         => $this->unit($request),
            'date_from'    => $request->date_from,
            'date_to'      => $request->date_to,
            // e.g. "1st Semester, A.Y. 2026-2027" - set by the Reports page period picker
            'period_label' => $request->period_label,
            'referrals'    => $this->buildReferralsReport($request),
            'cases'        => $this->buildCasesReport($request),
            'appointments' => $this->buildAppointmentsReport($request),
            'recurring'    => $this->buildRecurringReport($request),
            'services'     => $this->buildServicesReport($request),
        ];

        $pdf = Pdf::loadView('reports.export-pdf', $data)->setPaper('a4', 'portrait');

        return $pdf->download('iCARE-Report-' . now()->format('Y-m-d') . '.pdf');
    }

    public function exportExcel(Request $request)
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to'   => 'nullable|date',
            'period_label' => 'nullable|string|max:100',
        ]);

        if ($this->unit($request) === 'SDU') {
            return $this->exportSduExcel($request);
        }

        $referrals    = $this->buildReferralsReport($request);
        $cases        = $this->buildCasesReport($request);
        $appointments = $this->buildAppointmentsReport($request);
        $recurring    = $this->buildRecurringReport($request);

        $spreadsheet = new Spreadsheet();

        // ----- Summary sheet -----
        $summary = $spreadsheet->getActiveSheet();
        $summary->setTitle('Summary');
        $summary->fromArray([
            ['iCARE ' . $this->unit($request) . ' Report'],
            ['Generated: ' . now()->format('F j, Y g:i A')],
            [($request->period_label ? 'Period: ' . $request->period_label . ' | ' : '') . 'Date Range: ' . ($request->date_from ?: 'All time') . ' to ' . ($request->date_to ?: 'present')],
            [],
            ['Metric', 'Value'],
            ['Total Referrals', $referrals['total']],
            ['Total Cases', $cases['total']],
            ['Pending Cases', $cases['pending']],
            ['Avg. Days to Close', $cases['avg_days_to_close']],
            ['Total Appointments', $appointments['total']],
            ['Cases referred to TMDU', $cases['referred_tmdu']],
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

        // ----- By College sheet -----
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('By College');
        $sheet->fromArray(['College (of the referred student)', 'Referrals'], null, 'A1');
        $this->writeRows($sheet, $referrals['by_student_college'], ['college', 'count'], 2);

        // ----- Services Rendered sheet -----
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Services Rendered');
        $sheet->fromArray(['Service', 'Count'], null, 'A1');
        $this->writeRows($sheet, $this->buildServicesReport($request), ['service', 'count'], 2);

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

    private function exportSduExcel(Request $request)
    {
        $complaints = $this->buildComplaintsReport($request);

        $spreadsheet = new Spreadsheet();
        $summary = $spreadsheet->getActiveSheet();
        $summary->setTitle('Summary');
        $summary->fromArray(array_merge([
            ['iCARE SDU Report'],
            ['Generated: ' . now()->format('F j, Y g:i A')],
            [($request->period_label ? 'Period: ' . $request->period_label . ' | ' : '') . 'Date Range: ' . ($request->date_from ?: 'All time') . ' to ' . ($request->date_to ?: 'present')],
            [],
            ['Metric', 'Value'],
            ['Total Complaints', $complaints['total']],
        ], array_map(fn($row) => [$row['label'], $row['count']], $complaints['by_status'])), null, 'A1');

        foreach ([
            'By Misconduct' => [['Misconduct', 'Complaints'], $complaints['by_misconduct'], ['label', 'count']],
            'By College'    => [['College', 'Complaints'], $complaints['by_college'], ['label', 'count']],
            'By Department' => [['Department', 'College', 'Complaints'], $complaints['by_department'], ['label', 'college', 'count']],
        ] as $title => [$header, $rows, $keys]) {
            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle($title);
            $sheet->fromArray($header, null, 'A1');
            $this->writeRows($sheet, $rows, $keys, 2);
        }

        $spreadsheet->setActiveSheetIndex(0);

        $filename = 'iCARE-SDU-Report-' . now()->format('Y-m-d') . '.xlsx';
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