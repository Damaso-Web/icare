<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CaseFile;
use App\Models\CaseHandoff;
use App\Models\TestingRecord;
use App\Models\User;
use App\Notifications\UnreachableStudentNotification;
use Illuminate\Http\Request;

class CaseController extends Controller
{
    private function authorizeStaffAccess(): void
    {
        $user = request()->user();
        if (!in_array($user->role, ['admin', 'gcu_staff', 'sdu_head', 'tmdu_staff'])) {
            abort(403, 'Unauthorized. Only OSS staff may access case files.');
        }
    }

    public function index(Request $request)
    {
        $this->authorizeStaffAccess();

        $user = $request->user();

        $query = CaseFile::with(['student', 'counselor', 'latestReferral'])
            ->whereHas('student', fn($s) => $s->where('is_active', true))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->unit,   fn($q) => $q->where('current_unit', $request->unit))
            ->when($request->type,   fn($q) => $q->where('case_type', $request->type))
            ->when($request->has('requires_follow_up') && $request->requires_follow_up !== '', fn($q) => $q->where('requires_follow_up', true))
            ->when($request->search, fn($q) => $q->whereHas('student', fn($s) =>
                $s->where('first_name', 'like', "%{$request->search}%")
                  ->orWhere('last_name', 'like', "%{$request->search}%")
                  ->orWhere('student_id', 'like', "%{$request->search}%")
            ));

        if ($user->isTMDUStaff()) {
            $query->where('current_unit', 'TMDU');
        }

        if ($user->isSDUHead()) {
            $query->where('current_unit', 'SDU');
        }

        return response()->json($query->latest()->paginate(20));
    }

    public function show(Request $request, CaseFile $case)
    {
        $this->authorizeStaffAccess();

        $user = $request->user();
        $isOwner = $case->primary_counselor_id === $user->id;

        if ($isOwner) {
            AuditLog::record('viewed', "Viewed case {$case->case_number}.", $case);
        } else {
            AuditLog::record('viewed_by_other', "{$user->name} (not the assigned counselor) opened case {$case->case_number}.", $case);
        }

        $referralCount = $case->referrals()->count();

        return response()->json([
            ...$case->load([
                'student',
                'counselor',
                'referrals.referredBy',
                'sessionNotes.recordedBy',
                'appointments.staff',
                'testingRecord',
                'handoffs.fromUser',
                'handoffs.toUser',
                'documents',
                'interventions.personInCharge',
                'interventions.recordedBy',
                'interventions.completedBy',
                'interventions.referral',
            ])->toArray(),
            'latest_referral'      => $case->latestReferral()->with('referredBy')->first(),
            'client_status'        => $referralCount > 1 ? 'existing' : 'new',
            'prior_referral_count' => max(0, $referralCount - 1),
            'follow_up_count'      => $case->interventions()->where('type', 'follow_up')->count(),
            'parent_conference_count' => $case->interventions()->where('type', 'parent_conference')->count(),
                ]);
    }

    public function update(Request $request, CaseFile $case)
    {
        $this->authorizeStaffAccess();

        $old = $case->toArray();
        $case->update($request->only([
            'primary_counselor_id',
            'target_resolution_date',
            'presenting_concern',
            'background_info',
            'interventions_applied',
            'outcomes',
            'recommendations',
            'intake_notes',
        ]));

        if ($case->wasChanged('primary_counselor_id') && $case->counselor && $case->counselor->id !== $request->user()->id) {
            $case->counselor->notify(new \App\Notifications\CaseAssignedNotification(
                $case,
                "You have been assigned as primary counselor for case {$case->case_number}."
            ));
        }

        AuditLog::record('updated', "Updated case {$case->case_number}.", $case, $old, $case->toArray());
        return response()->json($case);
    }

    public function updateStatus(Request $request, CaseFile $case)
    {
        $this->authorizeStaffAccess();

        $request->validate([
            'status' => 'required|in:open,in_progress,awaiting_testing,awaiting_external,on_hold,resolved,closed'
        ]);
        $old = ['status' => $case->status];
        $case->update(['status' => $request->status]);
        AuditLog::record('status_updated', "Updated case {$case->case_number} status to {$request->status}.", $case, $old);
        return response()->json($case);
    }

    public function close(Request $request, CaseFile $case)
    {
        $this->authorizeStaffAccess();

        $request->validate([
            'interventions_applied' => 'required|string',
            'outcomes'              => 'required|string',
            'recommendations'       => 'nullable|string',
            'closure_summary'       => 'required|string',
        ]);

        $case->update([
            ...$request->only(['interventions_applied', 'outcomes', 'recommendations', 'closure_summary']),
            'status'      => 'closed',
            'closed_date' => today(),
        ]);

        AuditLog::record('closed', "Closed case {$case->case_number}.", $case);
        return response()->json($case);
    }

    public function summary(CaseFile $case)
    {
        $this->authorizeStaffAccess();

        AuditLog::record('exported', "Exported summary for case {$case->case_number}.", $case);
        return response()->json($case->load([
            'student',
            'counselor',
            'referrals',
            'sessionNotes',
            'testingRecord',
            'handoffs',
        ]));
    }

    public function referToTmdu(Request $request, CaseFile $case)
    {
        $this->authorizeStaffAccess();

        $request->validate(['reason' => 'required|string']);

        $testing = TestingRecord::create([
            'case_id'             => $case->id,
            'student_id'          => $case->student_id,
            'referred_by_user_id' => $request->user()->id,
            'status'              => 'pending',
        ]);

        $case->update([
            'referred_to_tmdu' => true,
            'current_unit'     => 'TMDU',
            'status'           => 'awaiting_testing',
        ]);

        CaseHandoff::create([
            'case_id'      => $case->id,
            'from_user_id' => $request->user()->id,
            'to_user_id'   => $request->user()->id,
            'from_unit'    => 'GCU',
            'to_unit'      => 'TMDU',
            'reason'       => $request->reason,
        ]);

        $tmduStaff = User::where('role', 'tmdu_staff')->where('is_active', true)->get();
        foreach ($tmduStaff as $staff) {
            $staff->notify(new \App\Notifications\CaseAssignedNotification(
                $case,
                "Case {$case->case_number} has been referred to TMDU for testing.",
                $request->reason
            ));
        }

        AuditLog::record('referred_tmdu', "Case {$case->case_number} referred to TMDU.", $case);
        return response()->json(['case' => $case, 'testing_record' => $testing]);
    }

    public function referExternal(Request $request, CaseFile $case)
    {
        $this->authorizeStaffAccess();

        $request->validate([
            'destination' => 'required|string',
            'reason'      => 'required|string',
        ]);

        $case->update([
            'referred_externally'           => true,
            'external_referral_destination' => $request->destination,
            'status'                        => 'awaiting_external',
        ]);

        AuditLog::record('referred_external', "Case {$case->case_number} referred externally to {$request->destination}.", $case);
        return response()->json($case);
    }

    public function handoff(Request $request, CaseFile $case)
    {
        $this->authorizeStaffAccess();

        $request->validate([
            'to_user_id' => 'required|exists:users,id',
            'to_unit'    => 'required|in:GCU,SDU,TMDU',
            'reason'     => 'required|string',
            'notes'      => 'nullable|string',
        ]);

        CaseHandoff::create([
            'case_id'      => $case->id,
            'from_user_id' => $request->user()->id,
            'to_user_id'   => $request->to_user_id,
            'from_unit'    => $case->current_unit,
            'to_unit'      => $request->to_unit,
            'reason'       => $request->reason,
            'notes'        => $request->notes,
        ]);

        $case->update(['current_unit' => $request->to_unit]);

        $toUser = \App\Models\User::find($request->to_user_id);
        if ($toUser) {
            $toUser->notify(new \App\Notifications\CaseAssignedNotification(
                $case,
                "Case {$case->case_number} has been handed off to you ({$request->to_unit}).",
                $request->notes
            ));
        }

        AuditLog::record('handoff', "Case {$case->case_number} handed off to {$request->to_unit}.", $case);
        return response()->json($case);
    }

    // Feature 55: Confirm receipt of an endorsed/handed-off case
    public function confirmHandoff(Request $request, \App\Models\CaseHandoff $handoff)
    {
        $this->authorizeStaffAccess();

        if ($handoff->to_user_id !== $request->user()->id) {
            abort(403, 'Only the receiving staff member may confirm this handoff.');
        }

        $handoff->update([
            'acknowledged'    => true,
            'acknowledged_at' => now(),
        ]);

        AuditLog::record('handoff_confirmed', "Confirmed receipt of case {$handoff->case->case_number} handoff.", $handoff);

        return response()->json($handoff);
    }

    // FR 2.7: Alert Dean's Secretary for Unreachable Students
    public function flagUnreachable(Request $request, CaseFile $case)
    {
        $this->authorizeStaffAccess();

        $request->validate([
            'notes' => 'nullable|string',
        ]);

        $case->update([
            'student_unreachable'     => true,
            'unreachable_flagged_at'  => now(),
            'unreachable_flagged_by'  => $request->user()->id,
            'unreachable_notes'       => $request->notes,
        ]);

        $deanSecretaries = User::where('role', 'dean_secretary')
            ->where('college', $case->student->college)
            ->where('is_active', true)
            ->get();

        foreach ($deanSecretaries as $secretary) {
            $secretary->notify(new UnreachableStudentNotification($case, $request->notes ?? ''));
        }

        AuditLog::record('unreachable_flagged', "Student flagged as unreachable for case {$case->case_number}.", $case);

        return response()->json([
            'message'  => 'Student flagged as unreachable. Dean\'s Secretary has been notified.',
            'case'     => $case,
            'notified' => $deanSecretaries->count(),
        ]);
    }

    public function flagFollowUp(Request $request, CaseFile $case)
    {
        $this->authorizeStaffAccess();

        $request->validate([
            'notes' => 'nullable|string',
        ]);

        $old = $case->toArray();
        $case->update([
            'requires_follow_up'   => true,
            'follow_up_notes'      => $request->notes,
            'follow_up_flagged_at' => now(),
            'follow_up_flagged_by' => $request->user()->id,
        ]);

        if ($case->counselor && $case->counselor->id !== $request->user()->id) {
            $case->counselor->notify(new \App\Notifications\CaseAssignedNotification(
                $case,
                "Case {$case->case_number} has been flagged as needing further attention.",
                $request->notes
            ));
        }

        AuditLog::record('follow_up_flagged', "Case {$case->case_number} flagged for further attention.", $case, $old, $case->toArray());

        return response()->json($case);
    }

    public function resolveFollowUp(Request $request, CaseFile $case)
    {
        $this->authorizeStaffAccess();

        $old = $case->toArray();
        $case->update(['requires_follow_up' => false]);

        AuditLog::record('follow_up_resolved', "Follow-up flag cleared for case {$case->case_number}.", $case, $old, $case->toArray());

        return response()->json($case);
    }

    // Feature 59: Dean's Secretary view of college cases needing follow-up
    public function collegeFollowUps(Request $request)
    {
        $user = $request->user();
        if (!$user->isDeanSecretary()) {
            abort(403, 'Unauthorized.');
        }

        $cases = CaseFile::where('requires_follow_up', true)
            ->whereHas('student', fn($s) => $s->where('college', $user->college))
            ->with(['student', 'counselor', 'latestReferral'])
            ->latest('follow_up_flagged_at')
            ->paginate(20);

        return response()->json($cases);
    }
}