<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\Referral;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;

// SDU's Student Incident Reports (IR). One IR per student: every complaint
// filed against the student, the IR's status, and the referrals SDU has made
// for that student (its handoffs to GCU). It is SDU's counterpart of GCU's
// Student Information File.
//
// An IR is not a table of its own - it is the student's complaints taken
// together. Its status is kept on those complaints (set on all of them at
// once), so the reports that count complaints by status stay correct.
class IncidentReportController extends Controller
{
    /** From least to most advanced; an IR is as far along as its least advanced complaint. */
    public const STATUSES = ['pending', 'under_review', 'resolved'];

    private function statusOf($complaints): string
    {
        foreach (self::STATUSES as $status) {
            if ($complaints->contains('status', $status)) {
                return $status;
            }
        }

        return 'pending';
    }

    /** Referrals SDU itself made for these students - its handoffs. */
    private function handoffs($studentIds)
    {
        $sduUserIds = User::where('role', 'sdu_head')->pluck('id');

        return Referral::whereIn('student_id', $studentIds)
            ->whereNull('complaint_id')   // the referral logged with a complaint is the complaint itself, not a handoff
            ->where(fn($q) => $q->whereIn('referrer_source', ['sdu', 'sdu_head'])
                ->orWhere('referrer_role', 'sdu_head')
                ->orWhereIn('referred_by_user_id', $sduUserIds))
            ->orderByDesc('created_at')
            ->get(['id', 'student_id', 'referral_code', 'referral_type', 'status', 'nature_of_concern', 'referrer_name', 'created_at']);
    }

    public function index(Request $request)
    {
        $complaints = Complaint::query()
            ->select('id', 'complainee_student_id', 'status', 'violation_type', 'created_at')
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('complainee_student_id');

        $students = Student::whereIn('id', $complaints->keys())
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->search;
                $q->where(fn($sq) => $sq->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('student_id', 'like', "%{$search}%"));
            })
            ->get(['id', 'student_id', 'first_name', 'middle_name', 'last_name', 'college', 'program', 'year_level']);

        $handoffs = $this->handoffs($students->pluck('id'))->groupBy('student_id');

        $rows = $students->map(function ($student) use ($complaints, $handoffs) {
            $own = $complaints[$student->id];

            return [
                'student'          => $student,
                'status'           => $this->statusOf($own),
                'complaints_count' => $own->count(),
                'latest_complaint' => $own->first()->created_at?->format('Y-m-d'),
                'latest_violation' => $own->first()->violation_type,
                'handoffs_count'   => ($handoffs[$student->id] ?? collect())->count(),
            ];
        })
            ->when($request->filled('status'), fn($c) => $c->where('status', $request->status))
            ->sortByDesc('latest_complaint')
            ->values();

        return response()->json([
            'data'   => $rows,
            'counts' => collect(self::STATUSES)->mapWithKeys(fn($s) => [$s => $rows->where('status', $s)->count()]),
        ]);
    }

    public function show(Student $student)
    {
        $complaints = Complaint::with(['filedBy:id,name', 'attachments'])
            ->where('complainee_student_id', $student->id)
            ->orderByDesc('created_at')
            ->get();

        abort_if($complaints->isEmpty(), 404, 'This student has no incident report.');

        $complaints->each(fn($c) => $c->attachments->each->append('url'));

        return response()->json([
            'student'    => $student->only(['id', 'student_id', 'first_name', 'middle_name', 'last_name', 'suffix', 'sex', 'college', 'program', 'year_level', 'section', 'email', 'contact_number', 'is_active']),
            'status'     => $this->statusOf($complaints),
            'complaints' => $complaints,
            'handoffs'   => $this->handoffs([$student->id]),
        ]);
    }

    public function updateStatus(Request $request, Student $student)
    {
        $validated = $request->validate([
            'status' => 'required|in:' . implode(',', self::STATUSES),
        ]);

        $count = Complaint::where('complainee_student_id', $student->id)->count();
        abort_if($count === 0, 404, 'This student has no incident report.');

        Complaint::where('complainee_student_id', $student->id)->update(['status' => $validated['status']]);

        AuditLog::record('status_updated', "Set the Incident Report of student {$student->student_id} to " . str_replace('_', ' ', $validated['status']) . " ({$count} complaint(s)).", $student);

        return $this->show($student);
    }
}
