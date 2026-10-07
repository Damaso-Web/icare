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
use App\Support\ReportWorkbook;

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

    /** The GCU Accomplishment Report sections, in the layout of the printed report. */
    public function gcuAccomplishment(Request $request)
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to'   => 'nullable|date',
        ]);
        abort_unless($this->unit($request) === 'GCU', 403, 'This report belongs to GCU.');

        return response()->json($this->buildGcuAccomplishment($request));
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

    // ---------- GCU Accomplishment Report ----------

    /**
     * Course codes as written in the printed GCU Accomplishment Report.
     * A course not listed here falls back to "BS <name>" / "BA <name>".
     */
    private const COURSE_CODES = [
        'bachelor of science in agribusiness'                            => 'BSAB',
        'bachelor of science in agriculture'                             => 'BSA',
        'bachelor of arts in communication'                              => 'BA Comm',
        'bachelor of arts in english language'                           => 'BAEL',
        'bachelor of arts in filipino language'                          => 'BAFL',
        'bachelor of science in agricultural and biosystems engineering' => 'BSABE',
        'bachelor of science in civil engineering'                       => 'BSCE',
        'bachelor of science in electrical engineering'                  => 'BSEE',
        'bachelor of science in industrial engineering'                  => 'BSIE',
        'bachelor of science in forestry'                                => 'BSF',
        'bachelor of science in entrepreneurship'                        => 'BS Entrep',
        'bachelor of science in food technology'                         => 'BSFT',
        'bachelor of science in hospitality management'                  => 'BSHM',
        'bachelor of science in nutrition and dietetics'                 => 'BSND',
        'bachelor of science in tourism management'                      => 'BSTM',
        'bachelor of physical education'                                 => 'BPEd',
        'bachelor of science in exercise and sports sciences'            => 'BSESS',
        'bachelor of science in development communication'               => 'BSDC',
        'bachelor of science in information technology'                  => 'BSIT',
        'bachelor of library and information science'                    => 'BLIS',
        'bachelor of science in biology'                                 => 'BS Bio',
        'bachelor of science in chemistry'                               => 'BS Chem',
        'bachelor of science in environmental science'                   => 'BSES',
        'bachelor of science in mathematics'                             => 'BS Math',
        'bachelor of science in statistics'                              => 'BSS',
        'bachelor of science in nursing'                                 => 'BSN',
        'bachelor of public administration'                              => 'BPA',
        'bachelor of arts in history'                                    => 'BA Hist',
        'bachelor of arts in psychology'                                 => 'BA Psych',
        'bachelor of science in psychology'                              => 'BS Psych',
        'bachelor of early childhood education'                          => 'BECED',
        'bachelor of elementary education'                               => 'BEED',
        'bachelor of secondary education'                                => 'BSED',
        'bachelor of technology and livelihood education'                => 'BTLED',
        'doctor of veterinary medicine'                                  => 'DVM',
        'doctor of medicine'                                             => 'MD',
    ];

    private function courseShort(?string $program): string
    {
        $program = trim((string) $program);
        if ($program === '') return 'Not specified';

        return self::COURSE_CODES[strtolower($program)] ?? preg_replace(
            ['/^Bachelor of Science in /i', '/^Bachelor of Arts in /i'],
            ['BS ', 'BA '],
            $program
        );
    }

    /** "College of Nursing (CN)" -> "CN" */
    private function collegeShort(?string $college): string
    {
        return preg_match('/\(([^)]+)\)\s*$/', (string) $college, $m) ? $m[1] : ($college ?: 'Not specified');
    }

    /** Master's and doctorate degrees go in the Graduate School table; DVM and MD stay with the colleges. */
    private function isGraduate(?string $program): bool
    {
        return (bool) preg_match('/^(Master|Doctor of Philosophy|Doctor of Education|Doctor in )/i', (string) $program);
    }

    /**
     * Groups students by college, then course, with Male / Female / Total -
     * undergraduate and graduate separately. With $allCourses every course
     * on file is listed (zeros included) in the printed report's order:
     * colleges by name, then their courses; students without a college go
     * last under "Outside".
     */
    private function inventoryGroups($students, bool $allCourses): array
    {
        $levels = ['undergraduate' => [], 'graduate' => []];
        $blank  = fn() => ['male' => 0, 'female' => 0, 'total' => 0];

        if ($allCourses) {
            $programs = DB::table('programs')->join('colleges', 'colleges.id', '=', 'programs.college_id')
                ->orderBy('colleges.name')->orderBy('programs.name')
                ->get(['colleges.name as college', 'programs.name as program']);
            foreach ($programs as $p) {
                $level = $this->isGraduate($p->program) ? 'graduate' : 'undergraduate';
                $levels[$level][$p->college][$p->program] = $blank();
            }
        }

        foreach ($students as $st) {
            $level   = $this->isGraduate($st->program) ? 'graduate' : 'undergraduate';
            $college = $st->college ?: 'Outside';
            $program = $st->program ?: ($st->college ? 'Not specified' : 'Outside');
            $row     = $levels[$level][$college][$program] ?? $blank();
            $sex     = strtolower((string) $st->sex);
            if ($sex === 'male')   $row['male']++;
            if ($sex === 'female') $row['female']++;
            $row['total']++;
            $levels[$level][$college][$program] = $row;
        }

        foreach ($levels as $level => $colleges) {
            // Colleges by name; "Outside" always last.
            uksort($colleges, fn($a, $b) => [$a === 'Outside', $a] <=> [$b === 'Outside', $b]);
            $groups = [];
            foreach ($colleges as $college => $programs) {
                ksort($programs);
                $rows = [];
                foreach ($programs as $program => $n) {
                    $rows[] = [
                        'course' => $level === 'graduate' || $program === 'Outside' ? $program : $this->courseShort($program),
                        'male'   => $n['male'], 'female' => $n['female'], 'total' => $n['total'],
                    ];
                }
                $groups[] = ['college' => $college === 'Outside' ? 'OUTSIDE' : $this->collegeShort($college), 'rows' => $rows];
            }
            $levels[$level] = $groups;
        }

        return $levels;
    }
    private function buildGcuAccomplishment(Request $request): array
    {
        $gcuReferrals = $this->forUnit(Referral::query(), 'GCU')
            ->when($request->date_from, fn($q) => $q->whereDate('created_at', '>=', $request->date_from))
            ->when($request->date_to,   fn($q) => $q->whereDate('created_at', '<=', $request->date_to));

        // A. Individual Inventory - each student referred to GCU in the period,
        //    i.e. each student whose Student Information File was opened or updated.
        $studentIds = $gcuReferrals->clone()->distinct()->pluck('student_id');
        $inventory  = $this->inventoryGroups(Student::whereIn('id', $studentIds)->get(['id', 'college', 'program', 'sex']), true);

        // 1. Counseling - students referred for counseling, per course.
        $counselingIds = $gcuReferrals->clone()->where('referral_type', 'counseling')->distinct()->pluck('student_id');
        $counseling    = $this->inventoryGroups(Student::whereIn('id', $counselingIds)->get(['id', 'college', 'program', 'sex']), false);

        // B. Services conducted - one transaction per referral of that kind.
        $typeCounts = $gcuReferrals->clone()->groupBy('referral_type')
            ->select('referral_type', DB::raw('count(*) as count'))->pluck('count', 'referral_type');

        // Referral types Admin may add later under Management > Referral Form
        // Options are matched to these rows by their label.
        $typeLabels = DB::table('referral_form_options')->where('category', 'referral_type')->pluck('label', 'value');
        $byLabel = function (string $pattern) use ($typeLabels, $typeCounts) {
            $total = 0;
            foreach ($typeLabels as $value => $label) {
                if (preg_match($pattern, $label)) $total += (int) ($typeCounts[$value] ?? 0);
            }
            return $total;
        };
        $between = fn($query, string $column) => $query
            ->when($request->date_from, fn($q) => $q->whereDate($column, '>=', $request->date_from))
            ->when($request->date_to,   fn($q) => $q->whereDate($column, '<=', $request->date_to));

        $services = [
            ['service' => 'Individual Guidance and/or Counseling',                                   'count' => (int) ($typeCounts['counseling'] ?? 0)],
            ['service' => 'Academic Coaching',                                                       'count' => (int) ($typeCounts['academic_deficiency'] ?? 0)],
            ['service' => 'Class Admission',                                                         'count' => (int) ($typeCounts['class_attendance'] ?? 0)],
            ['service' => 'Guidance & Counseling/Life Coaching of Students Applying for Re-admission', 'count' => (int) ($typeCounts['readmission'] ?? 0)],
            ['service' => 'Guidance & Counseling/Life Coaching of Students Applying Leave of Absence', 'count' => (int) ($typeCounts['leave_of_absence'] ?? 0)],
            ['service' => 'Guidance & Counseling/Life Coaching of Students Applying for Withdrawal',  'count' => (int) ($typeCounts['withdrawal'] ?? 0)],
            ['service' => 'Guidance & Counseling/Life Coaching of Students Applying for Dropping of Subjects', 'count' => $byLabel('/drop/i')],
            ['service' => 'Guidance & Counseling/Life Coaching of Students Applying for Shifting Course', 'count' => (int) ($typeCounts['shifting'] ?? 0)],
            ['service' => 'Guidance & Counseling/Life Coaching of Students Applying for Transferring Out', 'count' => $byLabel('/transfer/i')],
            ['service' => 'Parent/Guardian Conference',                                              'count' => $between(DB::table('parent_conference_slips'), 'created_at')->count()],
            ['service' => 'Briefing/Debriefing',                                                     'count' => $byLabel('/briefing/i')],
            ['service' => 'Career Guidance',                                                         'count' => $byLabel('/career/i')],
            ['service' => 'Inquiries',                                                               'count' => $byLabel('/inquir/i')],
            ['service' => 'Referral Inside (to TMDU)',                                               'count' => $between(TestingRecord::query(), 'created_at')->count()],
            ['service' => 'Referral Outside',                                                        'count' => $between(CaseFile::where('referred_externally', true), 'opened_date')->count()],
        ];

        return [
            'inventory'  => $inventory,
            'services'   => $services,
            'counseling' => $counseling,
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

        $unit = $this->unit($request);
        $unitNames = [
            'GCU'  => 'Guidance and Counseling Unit',
            'TMDU' => 'Testing and Measurement Development Unit',
            'SDU'  => 'Student Discipline Unit',
        ];
        $range = ($request->date_from ? date('F j, Y', strtotime($request->date_from)) : 'Start of records')
               . ' to ' . ($request->date_to ? date('F j, Y', strtotime($request->date_to)) : 'present');
        $period = ($request->period_label ? $request->period_label . '  |  ' : '') . $range;

        $book = new ReportWorkbook($unitNames[$unit], "{$unit} Accomplishment Report", $period, now()->format('F j, Y g:i A'));

        $unit === 'SDU' ? $this->fillSduWorkbook($book, $request) : $this->fillUnitWorkbook($book, $request, $unit);

        $filename = "iCARE-{$unit}-Report-" . now()->format('Y-m-d') . '.xlsx';

        return response()->download($book->save($filename), $filename)->deleteFileAfterSend(true);
    }

    /** "in_review" -> "In Review" */
    private function label(?string $value): string
    {
        return $value ? ucwords(str_replace('_', ' ', $value)) : 'Not specified';
    }

    /** GCU and TMDU workbook: summary, breakdowns and the detailed referral list. */
    private function fillUnitWorkbook(ReportWorkbook $book, Request $request, string $unit): void
    {
        if ($unit === 'GCU') {
            $this->fillGcuAccomplishmentSheets($book, $request);
        }
        $referrals    = $this->buildReferralsReport($request);
        $cases        = $this->buildCasesReport($request);
        $appointments = $this->buildAppointmentsReport($request);
        $recurring    = $this->buildRecurringReport($request);
        $services     = $this->buildServicesReport($request);

        $total = max(1, $referrals['total']);
        $share = fn(int $count) => $referrals['total'] ? $count / $total : 0;
        $byType = array_map(fn($r) => [$this->label($r['referral_type']), (int) $r['count'], $share((int) $r['count'])], $referrals['by_type']);
        usort($byType, fn($a, $b) => $b[1] <=> $a[1]);

        // ----- Summary -----
        $figures = [
            ['Total referrals received', (int) $referrals['total']],
            ['Total cases handled', (int) $cases['total']],
            ['Pending cases', (int) $cases['pending']],
            ['Average days to close a case', (float) $cases['avg_days_to_close']],
            ['Total appointments', (int) $appointments['total']],
            ['Students with recurring referrals', (int) $recurring['total_recurring_students']],
        ];
        if ($unit === 'GCU') {
            $figures[] = ['Cases referred to TMDU for testing', (int) $cases['referred_tmdu']];
        }

        $book->sheet('Summary', 'Summary of Accomplishments', [46, 16, 16])
            ->section('A. Key Figures')
            ->table(['Indicator', 'Number', ''], array_map(fn($r) => [$r[0], $r[1], ''], $figures), ['center' => [1]])
            ->section('B. Referrals Received, by Type')
            ->table(['Type of Referral', 'Number', '% of Total'], $byType, ['total' => [1], 'percent' => [2], 'center' => [1, 2]])
            ->section('C. Services Rendered')
            ->table(['Service', 'Number', ''], array_map(fn($r) => [$r['service'], (int) $r['count'], ''], $services), ['center' => [1]])
            ->signatures();

        // ----- Referrals -----
        $months = ['', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
        $book->sheet('Referrals', 'Referrals Received', [46, 16, 16])
            ->section('A. By Type of Referral')
            ->table(['Type of Referral', 'Number', '% of Total'], $byType, ['total' => [1], 'percent' => [2], 'center' => [1, 2]])
            ->section('B. By Status')
            ->table(['Status', 'Number', '% of Total'], array_map(fn($r) => [$this->label($r['status']), (int) $r['count'], $share((int) $r['count'])], $referrals['by_status']), ['total' => [1], 'percent' => [2], 'center' => [1, 2]])
            ->section('C. By Month')
            ->table(['Month', 'Year', 'Number'], array_map(fn($r) => [$months[(int) $r['month']], (int) $r['year'], (int) $r['count']], $referrals['monthly_trend']), ['total' => [2], 'center' => [1, 2]]);

        // ----- By College -----
        $book->sheet('By College', 'Referrals by College of the Student', [56, 16, 16])
            ->table(['College', 'Number', '% of Total'], array_map(fn($r) => [$r['college'], (int) $r['count'], $share((int) $r['count'])], $referrals['by_student_college']), ['total' => [1], 'percent' => [2], 'center' => [1, 2]]);

        // ----- Cases and Appointments -----
        $statusCount = fn(string $status) => (int) (collect($appointments['by_status'])->firstWhere('status', $status)['count'] ?? 0);
        $book->sheet('Cases & Appointments', 'Cases and Appointments', [46, 16, 16])
            ->section('A. Cases by Status')
            ->table(['Status', 'Number', ''], array_map(fn($r) => [$this->label($r['status']), (int) $r['count'], ''], $cases['by_status']), ['total' => [1], 'center' => [1]])
            ->section('B. Appointments by Status')
            ->table(['Status', 'Number', ''], array_map(fn($s) => [$this->label($s), $statusCount($s), ''], ['pending', 'confirmed', 'completed', 'cancelled', 'no_show']), ['total' => [1], 'center' => [1]])
            ->section('C. Appointments by Type')
            ->table(['Type of Appointment', 'Number', ''], array_map(fn($r) => [$this->label($r['appointment_type']), (int) $r['count'], ''], $appointments['by_type']), ['total' => [1], 'center' => [1]]);

        // ----- Recurring Concerns -----
        $book->sheet('Recurring Concerns', 'Recurring Concerns', [40, 16, 18, 20])
            ->table(['Type of Referral', 'Total Referrals', 'Distinct Students', 'Students Referred More Than Once'],
                array_map(fn($r) => [$this->label($r['referral_type']), (int) $r['total_referrals'], (int) $r['distinct_students'], (int) $r['recurring_students']], $recurring['by_type']),
                ['total' => [1], 'center' => [1, 2, 3]]);

        // ----- Detailed list -----
        // One row per referral. Student names and ID numbers are left out on
        // purpose: this file leaves the office, and guidance records are confidential.
        $rows = $this->forUnit(Referral::with('student'), $unit)
            ->when($request->date_from, fn($q) => $q->whereDate('created_at', '>=', $request->date_from))
            ->when($request->date_to,   fn($q) => $q->whereDate('created_at', '<=', $request->date_to))
            ->orderBy('created_at')
            ->get()
            ->values()
            ->map(fn($r, $i) => [
                $i + 1,
                $r->referral_code,
                $r->created_at?->format('M j, Y'),
                $r->student?->college ?: 'Not specified',
                $r->student?->program ?: 'Not specified',
                $r->student?->year_level ?: '-',
                $r->student?->sex ?: '-',
                $this->label($r->referral_type),
                $this->label($r->status),
            ])->all();

        $book->sheet('Referral List', 'Detailed List of Referrals', [6, 18, 14, 38, 38, 12, 10, 24, 16])
            ->landscape()
            ->table(['No.', 'Referral No.', 'Date Received', 'College', 'Program', 'Year Level', 'Sex', 'Type of Referral', 'Status'], $rows, ['center' => [0, 2, 5, 6], 'repeat' => true]);
    }

    /** The three sections of the printed GCU Accomplishment Report. */
    private function fillGcuAccomplishmentSheets(ReportWorkbook $book, Request $request): void
    {
        $data = $this->buildGcuAccomplishment($request);

        $book->sheet('A. Individual Inventory', 'Guidance and Counseling Unit by the Numbers', [16, 46, 13, 13, 13])
            ->section("A. Individual Inventory (updating of students' records in the SIAS and in the Anecdotal Record)")
            ->groupedTable('COLLEGE', 'INDIVIDUAL INVENTORY', $data['inventory']['undergraduate'])
            ->groupedTable('GRADUATE SCHOOL', 'INDIVIDUAL INVENTORY', $data['inventory']['graduate']);

        // Service names as worded in the printed report (Excel only; the page keeps the short names).
        $printed = [
            'Individual Guidance and/or Counseling'                                           => 'Individual Guidance and/or Counseling (TuTuKK: Kalinga)',
            'Academic Coaching'                                                               => 'Academic Coaching (TuTuKK: Kalinga)',
            'Class Admission'                                                                 => 'Class Admission (TuTuKK: Kalinga)',
            'Guidance & Counseling/Life Coaching of Students Applying for Re-admission'       => 'Guidance & Counseling/Life Coaching of Students Applying for Re-admission (TuTuKK: Kalinga)',
            'Guidance & Counseling/Life Coaching of Students Applying Leave of Absence'       => 'Guidance & Counseling/Life Coaching of Students Applying Leave of Absence (TuTuKK: Kalinga)',
            'Guidance & Counseling/Life Coaching of Students Applying for Withdrawal'          => 'Guidance & Counseling/Life Coaching of Students Applying for Withdrawal (TuTuKK: Kalinga)',
            'Guidance & Counseling/Life Coaching of Students Applying for Dropping of Subjects' => 'Guidance & Counseling/Life Coaching of Students Applying for dropping of subjects (TuTuKK: Kalinga)',
            'Guidance & Counseling/Life Coaching of Students Applying for Shifting Course'    => 'Guidance & Counseling/Life Coaching of Students Applying for shifting course (TuTuKK: Kalinga)',
            'Guidance & Counseling/Life Coaching of Students Applying for Transferring Out'   => 'Guidance & Counseling/Life Coaching of Students Applying for transferring out (TuTuKK: Kalinga)',
            'Parent/Guardian Conference'                                                      => 'Parent/ Guardian Conference (TuTuKK: Dap-ay)',
            'Briefing/Debriefing'                                                             => 'Briefing/ Debriefing (TuTuKK: Kalinga)',
            'Career Guidance'                                                                 => 'Career Guidance (TuTuKK: Kalinga)',
            'Referral Inside (to TMDU)'                                                       => 'Referral: Inside',
            'Referral Outside'                                                                => 'Referral: Outside',
        ];
        $book->sheet('B. Services Conducted', 'Guidance and Counseling Unit by the Numbers', [92, 18])
            ->line('B. Individual Guidance (TuTuKK: Kalinga)')
            ->line('Summary of Counseling/Life Coaching Services Conducted', true)
            ->servicesTable('TRANSACTIONS', array_map(fn($r) => [$printed[$r['service']] ?? $r['service'], (int) $r['count']], $data['services']));

        // The seven areas of concern of the printed report. iCARE does not record
        // the area yet, so these cells stay blank and only TOTAL is filled.
        $areas = [
            'academic'      => 'ACADEMIC',
            'behavioral'    => 'BEHAVIORAL',
            'environmental' => 'ENVIRONMENTAL',
            'personal'      => 'PERSONAL',
            'official'      => 'OFFICIAL/EXTRA CURRICULAR',
            'socio'         => 'SOCIO-CULTURAL',
            'psychosocial'  => 'PSYCHOSOCIAL',
        ];
        $book->sheet('1. Counseling', 'Guidance and Counseling Unit by the Numbers', array_merge([14, 34], array_fill(0, 14, 8.5), [10]))
            ->landscape()
            ->line('1. Counseling (TuTuKK: Kalinga)')
            ->counselingMatrix('COUNSELING', 'UNDERGRADUATE', $areas, $data['counseling']['undergraduate'])
            ->counselingMatrix('COUNSELING', 'GRADUATE SCHOOL', $areas, $data['counseling']['graduate'])
            ->note('Note: iCARE does not yet record the area of concern (academic, behavioral, environmental, personal, official/extra-curricular, socio-cultural, psychosocial) of a counseling case, so only the TOTAL per course is filled in.')
            ->signatures();
    }

    /** SDU workbook: complaint counts and the detailed complaint list. */
    private function fillSduWorkbook(ReportWorkbook $book, Request $request): void
    {
        $complaints = $this->buildComplaintsReport($request);
        $total = max(1, $complaints['total']);
        $share = fn(int $count) => $complaints['total'] ? $count / $total : 0;
        $counted = fn(array $rows) => array_map(fn($r) => [$r['label'], (int) $r['count'], $share((int) $r['count'])], $rows);

        $book->sheet('Summary', 'Summary of Complaints Received', [50, 16, 16])
            ->section('A. Complaints by Status')
            ->table(['Status', 'Number', '% of Total'], $counted($complaints['by_status']), ['total' => [1], 'percent' => [2], 'center' => [1, 2]])
            ->section('B. Complaints by Misconduct')
            ->table(['Misconduct', 'Number', '% of Total'], $counted($complaints['by_misconduct']), ['total' => [1], 'percent' => [2], 'center' => [1, 2]])
            ->signatures();

        $book->sheet('By College', 'Complaints by College', [56, 16, 16])
            ->table(['College', 'Number', '% of Total'], $counted($complaints['by_college']), ['total' => [1], 'percent' => [2], 'center' => [1, 2]]);

        $book->sheet('By Department', 'Complaints by Department', [40, 46, 14])
            ->table(['Department', 'College', 'Number'], array_map(fn($r) => [$r['label'], $r['college'], (int) $r['count']], $complaints['by_department']), ['total' => [2], 'center' => [2]]);

        // One row per complaint, without the names of the persons involved.
        $rows = Complaint::query()
            ->when($request->date_from, fn($q) => $q->whereDate('created_at', '>=', $request->date_from))
            ->when($request->date_to,   fn($q) => $q->whereDate('created_at', '<=', $request->date_to))
            ->orderBy('created_at')
            ->get()
            ->values()
            ->map(fn($c, $i) => [
                $i + 1,
                $c->complaint_code,
                $c->created_at?->format('M j, Y'),
                $c->incident_date ? date('M j, Y', strtotime($c->incident_date)) : '-',
                $c->complainee_college ?: 'Not specified',
                $c->complainee_department ?: 'Not specified',
                $c->violation_type ?: 'Not specified',
                $this->label($c->status),
            ])->all();

        $book->sheet('Complaint List', 'Detailed List of Complaints', [6, 18, 14, 14, 38, 28, 40, 16])
            ->landscape()
            ->table(['No.', 'Complaint No.', 'Date Filed', 'Date of Incident', 'College', 'Department', 'Misconduct', 'Status'], $rows, ['center' => [0, 2, 3], 'repeat' => true]);
    }
}
