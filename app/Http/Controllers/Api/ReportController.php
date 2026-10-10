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
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

    /** SDU only: the students SDU itself referred. */
    public function sduReferrals(Request $request)
    {
        abort_unless($this->unit($request) === 'SDU', 403, 'This report belongs to the Student Discipline Unit.');

        return response()->json($this->buildSduReferrals($request));
    }

    private const UNIT_BY_ROLE =['gcu_staff' => 'GCU', 'tmdu_staff' => 'TMDU', 'sdu_head' => 'SDU'];

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
            'by_source'       => $this->buildSourceBreakdown($query->clone()),
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

    private const SOURCE_LABELS = [
        'self'           => 'Self-referral (Student)',
        'faculty'        => 'Faculty',
        'dean'           => 'Dean',
        'dept_chair'     => 'Department Chair',
        'dean_secretary' => "Dean's Secretary",
        'parent'         => 'Parent / Guardian',
        'admin'          => 'GCU Office (Admin / GCU Head)',
        'gcu_staff'      => 'GCU Staff',
        'sdu'            => 'Student Discipline Unit',
        'tmdu'           => 'Testing and Measurement Development Unit',
        'none'           => 'Not specified',
    ];

    /** Who the referrals came from (faculty, dean, parent, the student...), highest first. */
    private function buildSourceBreakdown($query): array
    {
        $counts = [];
        foreach ($query->select('referrer_source', 'is_self_referred', DB::raw('count(*) as count'))
                     ->groupBy('referrer_source', 'is_self_referred')->get() as $row) {
            $key = $row->is_self_referred ? 'self' : (strtolower(trim((string) $row->referrer_source)) ?: 'none');
            $counts[$key] = ($counts[$key] ?? 0) + (int) $row->count;
        }

        $rows = [];
        foreach ($counts as $key => $count) {
            $rows[] = ['source' => self::SOURCE_LABELS[$key] ?? $this->label($key), 'count' => $count];
        }
        usort($rows, fn($a, $b) => [$b['count'], $a['source']] <=> [$a['count'], $b['source']]);

        return $rows;
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

    /**
     * SDU's Referrals report: the students SDU itself referred to the other
     * units (as opposed to the complaints it received). A referral counts
     * when its recorded source is SDU or it was filed from an SDU Head's account.
     */
    private function buildSduReferrals(Request $request): array
    {
        $sduUserIds = User::where('role', 'sdu_head')->pluck('id');

        $referrals = Referral::with('student:id,student_id,first_name,middle_name,last_name,college,program,year_level,sex')
            ->where(fn($q) => $q->where('referrer_source', 'sdu')
                ->orWhere('referrer_role', 'sdu_head')
                ->orWhereIn('referred_by_user_id', $sduUserIds))
            ->when($request->date_from, fn($q) => $q->whereDate('created_at', '>=', $request->date_from))
            ->when($request->date_to,   fn($q) => $q->whereDate('created_at', '<=', $request->date_to))
            ->orderByDesc('created_at')
            ->get();

        $tally = fn(string $field) => $referrals->groupBy($field)
            ->map(fn($group, $key) => ['label' => $this->label($key), 'count' => $group->count()])
            ->sortByDesc('count')->values()->all();

        return [
            'total'     => $referrals->count(),
            'students'  => $referrals->pluck('student_id')->unique()->count(),
            'by_type'   => $tally('referral_type'),
            'by_status' => $tally('status'),
            'list'      => $referrals->map(fn($r) => [
                'id'             => $r->id,
                'referral_code'  => $r->referral_code,
                'date'           => $r->created_at?->format('Y-m-d'),
                'student_pk'     => $r->student_id,
                'student_number' => $r->student?->student_id,
                'student_name'   => $r->student ? trim("{$r->student->last_name}, {$r->student->first_name} {$r->student->middle_name}") : 'Unknown student',
                'college'        => $r->student?->college ?: 'Not specified',
                'program'        => $r->student?->program ?: 'Not specified',
                'year_level'     => $r->student?->year_level ?: '-',
                'sex'            => $r->student?->sex ?: '-',
                'referral_type'  => $this->label($r->referral_type),
                'status'         => $this->label($r->status),
                'referred_by'    => $r->referrer_name ?: 'SDU',
            ])->all(),
        ];
    }

    /** SDU's Referrals report as sheets: a summary, then one row per referral. */
    private function fillSduReferralsWorkbook(ReportWorkbook $book, Request $request): void
    {
        $data  = $this->buildSduReferrals($request);
        $share = fn(int $count) => $data['total'] ? $count / $data['total'] : 0;

        $book->sheet('Summary', 'Students Referred by the Student Discipline Unit', [46, 16, 16])
            ->section('A. Key Figures')
            ->table(['Indicator', 'Number', ''], [
                ['Referrals made by SDU', $data['total'], ''],
                ['Students referred', $data['students'], ''],
            ], ['center' => [1]])
            ->section('B. By Type of Referral')
            ->table(['Type of Referral', 'Number', '% of Total'], array_map(fn($r) => [$r['label'], $r['count'], $share($r['count'])], $data['by_type']), ['total' => [1], 'percent' => [2], 'center' => [1, 2]])
            ->section('C. By Status')
            ->table(['Status', 'Number', '% of Total'], array_map(fn($r) => [$r['label'], $r['count'], $share($r['count'])], $data['by_status']), ['total' => [1], 'percent' => [2], 'center' => [1, 2]]);

        // Like every other export, the list carries no student names or ID
        // numbers: the file leaves the office.
        $rows = array_values(array_map(fn($r, $i) => [
            $i + 1, $r['referral_code'], $r['date'] ? date('M j, Y', strtotime($r['date'])) : '-',
            $r['college'], $r['program'], $r['year_level'], $r['sex'], $r['referral_type'], $r['status'],
        ], $data['list'], array_keys($data['list'])));

        $book->sheet('Students Referred', 'List of Students Referred by SDU', [6, 18, 14, 38, 38, 12, 10, 24, 16])
            ->table(['No.', 'Referral No.', 'Date Referred', 'College', 'Program', 'Year Level', 'Sex', 'Type of Referral', 'Status'], $rows, ['center' => [0, 2, 5, 6], 'repeat' => true])
            ->signatures();
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
            'attendance'     => $this->buildAttendance($query->clone()),
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

    /**
     * Attendance for appointments that reached their day: attended against
     * no-show. Cancelled ones are listed but kept out of the rate, since the
     * student was never expected.
     */
    private function buildAttendance($query): array
    {
        $by = $query->select('status', DB::raw('count(*) as count'))->groupBy('status')->pluck('count', 'status');
        $attended = (int) ($by['completed'] ?? 0);
        $noShow   = (int) ($by['no_show'] ?? 0);

        return [
            'attended'  => $attended,
            'no_show'   => $noShow,
            'cancelled' => (int) ($by['cancelled'] ?? 0),
            'upcoming'  => (int) ($by['pending'] ?? 0) + (int) ($by['confirmed'] ?? 0),
            'rate'      => ($attended + $noShow) ? round($attended / ($attended + $noShow) * 100, 1) : null,
        ];
    }

    /** Statuses that count as a finished case. */
    private const DONE_CASE_STATUSES = ['resolved', 'closed'];

    /** Open cases by how long they have been open, as of today. */
    private function buildPendingAging($query): array
    {
        $buckets = ['7 days or less' => 0, '8 to 30 days' => 0, '31 to 60 days' => 0, 'More than 60 days' => 0];
        foreach ($query->whereNotIn('status', self::DONE_CASE_STATUSES)->pluck('opened_date') as $opened) {
            $days = $opened ? (int) \Carbon\Carbon::parse($opened)->startOfDay()->diffInDays(now()->startOfDay()) : 0;
            $key = $days <= 7 ? '7 days or less' : ($days <= 30 ? '8 to 30 days' : ($days <= 60 ? '31 to 60 days' : 'More than 60 days'));
            $buckets[$key]++;
        }

        return array_map(fn($label, $count) => ['label' => $label, 'count' => $count], array_keys($buckets), $buckets);
    }

    /** The finished cases of the period, most recently closed first. */
    private function buildResolutions($query): array
    {
        return $query->whereIn('status', self::DONE_CASE_STATUSES)
            ->with('student:id,college,program')
            ->get()
            ->map(function ($c) {
                // Cases closed before the closing date was recorded automatically
                // fall back to when their status last changed.
                $closed = $c->closed_date ?? $c->status_changed_at;

                return [
                    'id'            => $c->id,
                    'student_id'    => $c->student_id,
                    'case_number'   => $c->case_number,
                    'case_type'     => $c->case_type,
                    'college'       => $c->student?->college ?: 'Not specified',
                    'status'        => $c->status,
                    'opened_date'   => $c->opened_date?->format('Y-m-d'),
                    'closed_date'   => $closed?->format('Y-m-d'),
                    'days_to_close' => ($c->opened_date && $closed)
                        ? (int) $c->opened_date->copy()->startOfDay()->diffInDays($closed->copy()->startOfDay())
                        : null,
                ];
            })
            ->sortByDesc('closed_date')->values()->all();
    }

    private function buildCasesReport(Request $request): array
    {
        $query = CaseFile::where('current_unit', $this->unit($request))
            ->when($request->date_from, fn($q) => $q->whereDate('opened_date', '>=', $request->date_from))
            ->when($request->date_to,   fn($q) => $q->whereDate('opened_date', '<=', $request->date_to));

        return [
            'total'          => $query->count(),
            'pending'        => $query->clone()->whereNotIn('status', self::DONE_CASE_STATUSES)->count(),
            'completed'      => $completed = $query->clone()->whereIn('status', self::DONE_CASE_STATUSES)->count(),
            // Share of the period's cases that are resolved or closed.
            'completion_rate' => ($total = $query->count()) ? round($completed / $total * 100, 1) : null,
            'pending_aging'  => $this->buildPendingAging($query->clone()),
            'resolutions'    => $this->buildResolutions($query->clone()),
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
                (clone $query)->whereIn('status', self::DONE_CASE_STATUSES)
                    ->avg(DB::raw('DATEDIFF(COALESCE(closed_date, DATE(status_changed_at)), opened_date)')) ?? 0,
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

    // ---------- Exports ----------
    // Excel and PDF carry the same report: the PDF is the same workbook,
    // every sheet printed in order, so the two can never drift apart.

    public function exportPdf(Request $request)
    {
        // Rendering the full report PDF can go past PHP's default 128 MB / 30 s.
        ini_set('memory_limit', '384M');
        set_time_limit(180);

        [$book, $unit, $file] = $this->makeWorkbook($request);
        $filename = "iCARE-{$unit}-{$file}-" . now()->format('Y-m-d') . '.pdf';

        return response()->download($book->savePdf($filename), $filename)->deleteFileAfterSend(true);
    }

    public function exportExcel(Request $request)
    {
        [$book, $unit, $file] = $this->makeWorkbook($request);
        $filename = "iCARE-{$unit}-{$file}-" . now()->format('Y-m-d') . '.xlsx';

        return response()->download($book->save($filename), $filename)->deleteFileAfterSend(true);
    }

    /** Builds the unit's report workbook, shared by both exports. */
    private function makeWorkbook(Request $request): array
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to'   => 'nullable|date',
            'period_label' => 'nullable|string|max:100',
            'report'    => 'nullable|in:year_end,referrals,cases,appointments',
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

        // The year-end report is the unit's full accomplishment report; the
        // other three are its referral, case and appointment parts on their own.
        $report = $request->input('report') ?: 'year_end';
        if ($unit === 'SDU' && $report !== 'referrals') {
            $report = 'year_end';
        }
        [$title, $file] = [
            'year_end'     => ['Accomplishment Report', 'Report'],
            'referrals'    => ['Referrals Report', 'Referrals-Report'],
            'cases'        => ['Case Report', 'Case-Report'],
            'appointments' => ['Appointment Report', 'Appointment-Report'],
        ][$report];

        $book = new ReportWorkbook($unitNames[$unit], "{$unit} {$title}", $period, now()->format('F j, Y g:i A'));

        // SDU has two reports of its own: year-end (its complaints) and
        // referrals (the students it referred). The other units share the same sheets.
        if ($unit === 'SDU') {
            $report === 'referrals' ? $this->fillSduReferralsWorkbook($book, $request) : $this->fillSduWorkbook($book, $request);
        } else {
            $this->fillUnitWorkbook($book, $request, $unit, $report);
        }

        return [$book, $unit, $file];
    }

    /** "in_review" -> "In Review" */
    private function label(?string $value): string
    {
        return $value ? ucwords(str_replace('_', ' ', $value)) : 'Not specified';
    }

    /** GCU and TMDU workbook: summary, breakdowns and the detailed referral list. */
    private function fillUnitWorkbook(ReportWorkbook $book, Request $request, string $unit, string $report = 'year_end'): void
    {
        // A sheet is printed in the year-end report and in the report it belongs to.
        $in = fn(string $part) => $report === 'year_end' || $report === $part;

        if ($unit === 'GCU' && $report === 'year_end') {
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

        if ($report === 'year_end') {
            // ----- Summary -----
            $figures = [
                ['Total referrals received', (int) $referrals['total']],
                ['Total cases handled', (int) $cases['total']],
                ['Pending cases', (int) $cases['pending']],
                ['Resolved / closed cases', (int) $cases['completed']],
                ['Case completion rate', $cases['completion_rate'] === null ? '-' : $cases['completion_rate'] . '%'],
                ['Average days to close a case', (float) $cases['avg_days_to_close']],
                ['Total appointments', (int) $appointments['total']],
                ['Appointment attendance rate', $appointments['attendance']['rate'] === null ? '-' : $appointments['attendance']['rate'] . '%'],
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
        }

        if ($in('referrals')) {
            // ----- Referrals -----
            $months = ['', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
            $book->sheet('Referrals', 'Referrals Received', [46, 16, 16])
                ->section('A. By Type of Referral')
                ->table(['Type of Referral', 'Number', '% of Total'], $byType, ['total' => [1], 'percent' => [2], 'center' => [1, 2]])
                ->section('B. By Source of Referral')
                ->table(['Referred By', 'Number', '% of Total'], array_map(fn($r) => [$r['source'], (int) $r['count'], $share((int) $r['count'])], $referrals['by_source']), ['total' => [1], 'percent' => [2], 'center' => [1, 2]])
                ->section('C. By Status')
                ->table(['Status', 'Number', '% of Total'], array_map(fn($r) => [$this->label($r['status']), (int) $r['count'], $share((int) $r['count'])], $referrals['by_status']), ['total' => [1], 'percent' => [2], 'center' => [1, 2]]);

            // ----- By College and by Month -----
            $book->sheet('By College', 'Referrals by College and by Month', [56, 16, 16])
                ->section('A. By College of the Student')
                ->table(['College', 'Number', '% of Total'], array_map(fn($r) => [$r['college'], (int) $r['count'], $share((int) $r['count'])], $referrals['by_student_college']), ['total' => [1], 'percent' => [2], 'center' => [1, 2]])
                ->section('B. By Month')
                ->table(['Month', 'Year', 'Number'], array_map(fn($r) => [$months[(int) $r['month']], (int) $r['year'], (int) $r['count']], $referrals['monthly_trend']), ['total' => [2], 'center' => [1, 2]]);
        }

        if ($in('cases')) {
            // ----- Cases: status, completion and backlog -----
            $caseShare = fn(int $count) => $cases['total'] ? $count / $cases['total'] : 0;
            $book->sheet('Cases', 'Cases: Status, Completion and Backlog', [46, 16, 16])
                ->section('A. Cases by Status')
                ->table(['Status', 'Number', '% of Total'], array_map(fn($r) => [$this->label($r['status']), (int) $r['count'], $caseShare((int) $r['count'])], $cases['by_status']), ['total' => [1], 'percent' => [2], 'center' => [1, 2]])
                ->section('B. Case Completion: Resolved Against Open Cases')
                ->table(['Cases', 'Number', '% of Total'], [
                    ['Resolved / closed', (int) $cases['completed'], $caseShare((int) $cases['completed'])],
                    ['Open / pending', (int) $cases['pending'], $caseShare((int) $cases['pending'])],
                ], ['total' => [1], 'percent' => [2], 'center' => [1, 2]])
                ->section('C. Pending Cases, by How Long They Have Been Open')
                ->table(['Open For', 'Number', ''], array_map(fn($r) => [$r['label'], (int) $r['count'], ''], $cases['pending_aging']), ['total' => [1], 'center' => [1]]);

            // ----- Case resolutions -----
            // Case numbers only - no student names leave the office.
            $caseDate = fn(?string $d) => $d ? date('M j, Y', strtotime($d)) : '-';
            $book->sheet('Case Resolutions', 'Resolved and Closed Cases', [8, 22, 30, 44, 18, 18, 14, 16])
                ->table(['No.', 'Case No.', 'Concern', 'College', 'Date Opened', 'Date Closed', 'Days to Close', 'Status'],
                    array_values(array_map(fn($r, $i) => [
                        $i + 1, $r['case_number'], $this->label($r['case_type']), $r['college'],
                        $caseDate($r['opened_date']), $caseDate($r['closed_date']), $r['days_to_close'] ?? '-', $this->label($r['status']),
                    ], $cases['resolutions'], array_keys($cases['resolutions']))),
                    ['center' => [0, 4, 5, 6], 'repeat' => true]);
        }

        if ($in('appointments')) {
            // ----- Appointments -----
            $statusCount = fn(string $status) => (int) (collect($appointments['by_status'])->firstWhere('status', $status)['count'] ?? 0);
            $attendance  = $appointments['attendance'];
            $book->sheet('Appointments', 'Appointments and Attendance', [46, 16, 16])
                ->section('A. Appointments by Status')
                ->table(['Status', 'Number', ''], array_map(fn($s) => [$this->label($s), $statusCount($s), ''], ['pending', 'confirmed', 'completed', 'cancelled', 'no_show']), ['total' => [1], 'center' => [1]])
                ->section('B. Attendance')
                ->table(['Outcome', 'Number', ''], [
                    ['Attended', $attendance['attended'], ''],
                    ['No-show', $attendance['no_show'], ''],
                    ['Cancelled', $attendance['cancelled'], ''],
                    ['Attendance rate (attended against no-show)', $attendance['rate'] === null ? '-' : $attendance['rate'] . '%', ''],
                ], ['center' => [1]])
                ->section('C. Appointments by Type')
                ->table(['Type of Appointment', 'Number', ''], array_map(fn($r) => [$this->label($r['appointment_type']), (int) $r['count'], ''], $appointments['by_type']), ['total' => [1], 'center' => [1]]);
        }

        if ($in('referrals')) {
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
                ->table(['No.', 'Referral No.', 'Date Received', 'College', 'Program', 'Year Level', 'Sex', 'Type of Referral', 'Status'], $rows, ['center' => [0, 2, 5, 6], 'repeat' => true]);
        }

        // The year-end report is signed on its Summary sheet; the others on their last sheet.
        if ($report !== 'year_end') {
            $book->signatures();
        }
    }

    /** The three sections of the printed GCU Accomplishment Report. */
    private function fillGcuAccomplishmentSheets(ReportWorkbook $book, Request $request): void
    {
        $data = $this->buildGcuAccomplishment($request);

        $book->sheet('Individual Inventory', 'Guidance and Counseling Unit by the Numbers', [16, 46, 13, 13, 13])
            ->section("Individual Inventory (updating of students' records in the SIAS and in the Anecdotal Record)")
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
        $book->sheet('Services Conducted', 'Guidance and Counseling Unit by the Numbers', [92, 18])
            ->line('Individual Guidance (TuTuKK: Kalinga)')
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
        $book->sheet('Counseling', 'Guidance and Counseling Unit by the Numbers', array_merge([14, 34], array_fill(0, 14, 8.5), [10]))
            ->line('Counseling (TuTuKK: Kalinga)')
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
            ->table(['No.', 'Complaint No.', 'Date Filed', 'Date of Incident', 'College', 'Department', 'Misconduct', 'Status'], $rows, ['center' => [0, 2, 3], 'repeat' => true]);
    }
}
