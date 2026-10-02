<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CaseFile;
use App\Models\CaseHandoff;
use App\Models\Referral;
use App\Models\TestingRecord;
use App\Models\User;
use App\Notifications\CaseHandoffNotification;
use App\Notifications\HandoffAcknowledgedNotification;
use App\Notifications\TestingReferralNotification;
use App\Notifications\ReferredToTmduNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class CaseController extends Controller
{
    private function authorizeStaffAccess(): void
    {
        $user = request()->user();
        if (!in_array($user->role, ['admin', 'gcu_staff', 'sdu_head', 'tmdu_staff'])) {
            abort(403, 'Unauthorized. Only OSS staff may access case files.');
        }
    }

    /**
     * Only the GCU Head (admin) and gcu_staff may write to a SIF (CaseFile).
     * SDU and TMDU are read-only on cases.
     */
    private function authorizeCaseWriter(): void
    {
        $role = request()->user()?->role;
        if (!in_array($role, ['admin', 'gcu_staff'], true)) {
            abort(403, 'Unauthorized. Only GCU staff may modify case files.');
        }
    }

    private function authorizeUnitAccess(CaseFile $case): void
    {
        $user = request()->user();
        if ($user->isTMDUStaff() && $case->current_unit !== 'TMDU') {
            abort(403, 'Unauthorized. This case belongs to a different unit.');
        }
        if ($user->isSDUHead() && $case->current_unit !== 'SDU') {
            abort(403, 'Unauthorized. This case belongs to a different unit.');
        }
    }

    public function index(Request $request)
    {
        $this->authorizeStaffAccess();

        $user = $request->user();

        $query = CaseFile::with(['student', 'counselor', 'latestReferral'])
            ->whereHas('student', fn($s) => $s->where('is_active', true))
            // A case only belongs in Student Information Files once GCU has
            // acknowledged at least one of its referrals - an unacknowledged
            // referral's placeholder case shouldn't show up here yet.
            ->whereHas('referrals', fn($r) => $r->whereNotNull('acknowledged_at'))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->unit,   fn($q) => $q->where('current_unit', $request->unit))
            ->when($request->type,   fn($q) => $q->where('case_type', $request->type))
            ->when($request->filled('requires_follow_up'), fn($q) => $q->where('requires_follow_up', $request->boolean('requires_follow_up')))
            // Previously this only matched against the student's name/ID, never
            // the case number itself - so typing a case number (e.g. "001")
            // silently fell through to a student_id substring match instead,
            // surfacing unrelated cases. Search both, grouped so it still ANDs
            // correctly with the other filters above.
            ->when($request->search, function ($q) use ($request) {
                $search = $request->search;
                $q->where(function ($qq) use ($search) {
                    $qq->where('case_number', 'like', "%{$search}%")
                       ->orWhereHas('student', fn($s) =>
                            $s->where('first_name', 'like', "%{$search}%")
                              ->orWhere('last_name', 'like', "%{$search}%")
                              ->orWhere('student_id', 'like', "%{$search}%")
                        );
                });
            });

        if ($user->isTMDUStaff()) {
            $query->where('current_unit', 'TMDU');
        }

        if ($user->isSDUHead()) {
            $query->where('current_unit', 'SDU');
        }

        return response()->json(
            $query->orderByRaw("CASE WHEN status IN ('resolved','closed') THEN 1 ELSE 0 END ASC")
                ->orderBy('created_at', 'desc')
                ->paginate(20)
        );
    }

    public function show(CaseFile $case)
    {
        $this->authorizeStaffAccess();
        $this->authorizeUnitAccess($case);

        AuditLog::record('viewed', "Viewed case {$case->case_number}.", $case);

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
            ])->toArray(),
            'client_status'        => $referralCount > 1 ? 'existing' : 'new',
            'prior_referral_count' => max(0, $referralCount - 1),
        ]);
    }

    public function update(Request $request, CaseFile $case)
    {
        $this->authorizeStaffAccess();
        $this->authorizeCaseWriter();
        $this->authorizeUnitAccess($case);

        $old = $case->toArray();
        $case->update($request->only([
            'primary_counselor_id',
            'target_resolution_date',
            'presenting_concern',
            'interventions_applied',
            'outcomes',
            'recommendations',
            'intake_notes',
        ]));
        AuditLog::record('updated', "Updated case {$case->case_number}.", $case, $old, $case->toArray());
        return response()->json($case->load('counselor'));
    }

    public function updateStatus(Request $request, CaseFile $case)
    {
        $this->authorizeStaffAccess();
        $this->authorizeCaseWriter();

        $request->validate([
            'status' => 'required|in:open,on_observation,in_progress,awaiting_testing,awaiting_external,on_hold,resolved,closed'
        ]);
        $old = ['status' => $case->status];
        $case->update(['status' => $request->status]);
        AuditLog::record('status_updated', "Updated case {$case->case_number} status to {$request->status}.", $case, $old);
        return response()->json($case);
    }

    public function close(Request $request, CaseFile $case)
    {
        $this->authorizeStaffAccess();
        $this->authorizeCaseWriter();

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
        $this->authorizeCaseWriter();

        $request->validate(['reason' => 'required|string']);

        $user = $request->user();

        // An appointment must be set AND attended before this case can be
        // escalated to TMDU - previously a referral could be sent off to
        // TMDU before the student had even shown up to anything.
        if (!$case->hasAttendedAppointment()) {
            abort(422, 'An appointment must be set and the student must have attended it before this case can be referred to TMDU.');
        }

        // If the student already has a Testing Record that hasn't been
        // finished yet (not 'test_results_issued'), this is just another
        // escalation into that SAME record and its SAME referral, rather
        // than forking off a brand new pair of them - re-referring a
        // student TMDU is already working with doesn't fork off a second,
        // disconnected engagement.
        //
        // This also fixes "Refer to TMDU" creating a duplicate Referral row
        // every time it was clicked for the same case: the referral's own
        // `status` never gets synced when TMDU finishes testing (nothing
        // sets it to completed/closed), so checking the referral's status
        // directly can't tell "still in progress" from "done, go again" -
        // but the TestingRecord's status always can, so that is what both
        // the referral and the testing record now key off, together.
        $testing = TestingRecord::where('student_id', $case->student_id)
            ->where('status', '!=', 'test_results_issued')
            ->latest()
            ->first();

        // Tracked separately so the response can tell the frontend whether
        // this click actually created a new referral or just updated the
        // one already open, so the UI can message it accurately instead of
        // always saying "New referral created".
        $referralWasReused = (bool) ($testing && $testing->referral);
        $referral = $referralWasReused ? $testing->referral : null;

        if ($referral) {
            $referral->update(['nature_of_concern' => $request->reason]);
        } else {
            $referral = Referral::create([
                'student_id'          => $case->student_id,
                'case_id'             => $case->id,
                'referred_by_user_id' => $user->id,
                'referrer_name'       => $user->name,
                'referrer_role'       => $user->role,
                'referrer_college'    => $user->college,
                'referral_type'       => 'psychological_testing',
                'nature_of_concern'   => $request->reason,
                'status'              => 'submitted',
            ]);
        }

        if ($testing) {
            $testing->update([
                'referral_id'         => $referral->id,
                'case_id'             => $case->id,
                'referred_by_user_id' => $user->id,
                'reason'              => $request->reason,
            ]);
        } else {
            $testing = TestingRecord::create([
                'case_id'             => $case->id,
                'referral_id'         => $referral->id,
                'student_id'          => $case->student_id,
                'referred_by_user_id' => $user->id,
                'reason'              => $request->reason,
                'status'              => 'pending',
            ]);
        }

        $case->update([
            'referred_to_tmdu' => true,
            'current_unit'     => 'TMDU',
            'status'           => 'awaiting_testing',
        ]);

        // Tagged with the referral this escalation was actually made from
        // (not just the case) so it only shows up in that one referral's own
        // Handoff/Endorsement History instead of bleeding into every other
        // referral under the same case - see Referral Show.vue's
        // caseHandoffs computed, which keys off this column.
        CaseHandoff::create([
            'case_id'      => $case->id,
            'referral_id'  => $referral->id,
            'from_user_id' => $user->id,
            'to_user_id'   => $user->id,
            'from_unit'    => 'GCU',
            'to_unit'      => 'TMDU',
            'reason'       => $request->reason,
        ]);

        AuditLog::record('referred_tmdu', "Case {$case->case_number} referred to TMDU (referral {$referral->referral_code}).", $case);

        $tmduStaff = User::where('role', 'tmdu_staff')->where('is_active', true)->get();
        Notification::send($tmduStaff, new TestingReferralNotification($case));

        // Let the student know they've been referred to TMDU for testing -
        // separate from the TMDU-staff notification above.
        if ($case->student) {
            Notification::send($case->student, new ReferredToTmduNotification($case));
        }

        return response()->json([
            'case' => $case,
            'referral' => $referral,
            'testing_record' => $testing,
            'referral_was_reused' => $referralWasReused,
        ]);
    }

    public function referExternal(Request $request, CaseFile $case)
    {
        $this->authorizeStaffAccess();
        $this->authorizeCaseWriter();

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
        $this->authorizeCaseWriter();

        $request->validate([
            'to_user_id'  => 'required|exists:users,id',
            'to_unit'     => 'required|in:GCU,SDU,TMDU',
            'reason'      => 'required|string',
            'notes'       => 'nullable|string',
            // Which of the case's referrals this endorsement was made from
            // (Show.vue sends the referral it's currently viewing). Optional
            // and validated loosely to this case, so an older caller that
            // doesn't send it yet still works - the handoff just won't be
            // scoped to one referral (see CaseHandoff::referral()).
            'referral_id' => 'nullable|integer|exists:referrals,id',
        ]);

        $handoff = CaseHandoff::create([
            'case_id'      => $case->id,
            'referral_id'  => $request->referral_id,
            'from_user_id' => $request->user()->id,
            'to_user_id'   => $request->to_user_id,
            'from_unit'    => $case->current_unit,
            'to_unit'      => $request->to_unit,
            'reason'       => $request->reason,
            'notes'        => $request->notes,
        ]);

        $case->update(['current_unit' => $request->to_unit]);

        if ($handoff->toUser) {
            Notification::send($handoff->toUser, new CaseHandoffNotification($handoff));
        }

        AuditLog::record('handoff', "Case {$case->case_number} handed off to {$request->to_unit}.", $case);
        return response()->json($case);
    }

    public function acknowledgeHandoff(Request $request, CaseFile $case, CaseHandoff $handoff)
    {
        $this->authorizeStaffAccess();
        $this->authorizeCaseWriter();

        if ($handoff->case_id !== $case->id) {
            abort(422, 'This handoff does not belong to this case.');
        }

        $handoff->update([
            'acknowledged'    => true,
            'acknowledged_at' => now(),
        ]);

        AuditLog::record('handoff_acknowledged', "Confirmed receipt of case {$case->case_number} handed off to {$handoff->to_unit}.", $case);

        if ($handoff->fromUser) {
            Notification::send($handoff->fromUser, new HandoffAcknowledgedNotification($handoff));
        }

        return response()->json($handoff->load(['fromUser', 'toUser']));
    }

    public function flagFollowUp(Request $request, CaseFile $case)
    {
        $this->authorizeStaffAccess();
        $this->authorizeCaseWriter();

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

        AuditLog::record('follow_up_flagged', "Case {$case->case_number} flagged for further attention.", $case, $old, $case->toArray());

        return response()->json($case);
    }

    public function resolveFollowUp(Request $request, CaseFile $case)
    {
        $this->authorizeStaffAccess();
        $this->authorizeCaseWriter();

        $old = $case->toArray();
        $case->update(['requires_follow_up' => false]);

        AuditLog::record('follow_up_resolved', "Follow-up flag cleared for case {$case->case_number}.", $case, $old, $case->toArray());

        return response()->json($case);
    }
}