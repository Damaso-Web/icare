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
use App\Notifications\ParentConferenceSlipNotification;
use App\Notifications\UnreachableStudentNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class CaseController extends Controller
{
    // Only for plain <input> fields (e.g. Issue Parent Conference Slip's
    // "reason"), never for free-text <textarea>-backed fields, which just
    // get a max length below and keep normal punctuation.
    private const TEXT_REGEX = '/^[a-zA-Z0-9\x{00C0}-\x{024F}\'\-\.\,\&\(\)\s]+$/u';

    private function authorizeStaffAccess(): void
    {
        $user = request()->user();
        // TMDU has no Student Information File access - they work from the
        // Testing Records module instead.
        if (!in_array($user->role, ['admin', 'gcu_staff', 'sdu_head'])) {
            abort(403, 'Unauthorized. Only GCU/SDU staff may access case files.');
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
                'parentConferenceSlips.issuedBy',
                'parentConferenceSlips.notesRecordedBy',
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
            'interventions_applied' => 'required|string|max:3000',
            'outcomes'              => 'required|string|max:3000',
            'recommendations'       => 'nullable|string|max:2000',
            'closure_summary'       => 'required|string|max:3000',
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
            'referrals.sessionNotes.recordedBy',
            'referrals.referredBy',
            'sessionNotes.recordedBy',
            'sessionNotes.referral',
            'testingRecord',
            'handoffs',
        ]));
    }

    public function referToTmdu(Request $request, CaseFile $case)
    {
        $this->authorizeStaffAccess();
        $this->authorizeCaseWriter();

        // referral_id is the GCU-side referral the staff member actually
        // clicked "Refer to TMDU" from (referrals/Show.vue sends
        // referral.value.id) - this is what scopes the escalation to that
        // one referral instead of sharing it across every referral on the
        // case. See $sourceReferral below.
        $request->validate([
            'reason'      => 'required|string|max:1000',
            'referral_id' => 'required|exists:referrals,id',
        ]);

        $user = $request->user();
        $sourceReferral = Referral::findOrFail($request->referral_id);

        // The referral this escalation is actually being made from must be
        // acknowledged AND have an attended appointment before it can be
        // referred to TMDU - previously this only checked the case had any
        // attended appointment at all, without requiring the referral
        // itself to have been picked up (acknowledged) first. Checked on
        // $sourceReferral (not just $case) so this stays consistent with
        // the referral-level SIF edit gate (Referral::canEditSif()).
        if (!$sourceReferral->canEditSif()) {
            abort(422, 'This referral must be acknowledged and have an appointment set and attended before it can be referred to TMDU.');
        }

        // Scoped per-referral, not per-case: re-clicking "Refer to TMDU"
        // from the SAME referral reuses that referral's own unfinished
        // Testing Record (not 'test_results_issued' yet) rather than
        // forking off a brand new pair of them. A DIFFERENT referral under
        // the same case (one student has one case for life, so it's common
        // for several unrelated referrals to share a case) always starts
        // its own, independent escalation instead - it is never blocked or
        // merged into a TMDU escalation some other referral already has in
        // progress. source_referral_id is what makes this possible; records
        // created before that column existed won't have one and so won't
        // be matched here (treated as belonging to no particular referral).
        //
        // This also fixes "Refer to TMDU" creating a duplicate Referral row
        // every time it was clicked for the same referral: the referral's
        // own `status` never gets synced when TMDU finishes testing
        // (nothing sets it to completed/closed), so checking the referral's
        // status directly can't tell "still in progress" from "done, go
        // again" - but the TestingRecord's status always can, so that is
        // what both the referral and the testing record now key off,
        // together.
        $testing = TestingRecord::where('source_referral_id', $sourceReferral->id)
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
                'source_referral_id'  => $sourceReferral->id,
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
            'destination' => 'required|string|max:255',
            'reason'      => 'required|string|max:1000',
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
            'reason'      => 'required|string|max:1000',
            'notes'       => 'nullable|string|max:1000',
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

    // "Flag Unreachable" (cases/Show.vue). Marks the case's student as
    // unreachable and notifies the active Dean's Secretary(ies) of the
    // student's college so they can help locate/contact the student.
    // The route and the notification class already existed, but this
    // controller method did not - so the button errored out.
    public function flagUnreachable(Request $request, CaseFile $case)
    {
        $this->authorizeStaffAccess();
        $this->authorizeCaseWriter();

        $validated = $request->validate([
            'notes' => 'nullable|string|max:1000',
        ]);

        $case->loadMissing('student');
        $notes = $validated['notes'] ?? '';

        $old = $case->toArray();
        $case->update([
            'student_unreachable'    => true,
            'unreachable_flagged_at' => now(),
            'unreachable_flagged_by' => $request->user()->id,
            'unreachable_notes'      => $notes !== '' ? $notes : null,
        ]);

        AuditLog::record('flagged_unreachable', "Flagged student on case {$case->case_number} as unreachable.", $case, $old, $case->toArray());

        if ($case->student) {
            $deanSecretaries = User::where('role', 'dean_secretary')
                ->where('college', $case->student->college)
                ->where('is_active', true)
                ->get();

            if ($deanSecretaries->isNotEmpty()) {
                Notification::send($deanSecretaries, new UnreachableStudentNotification($case, $notes));
            }
        }

        return response()->json($case);
    }

    public function flagFollowUp(Request $request, CaseFile $case)
    {
        $this->authorizeStaffAccess();
        $this->authorizeCaseWriter();

        $request->validate([
            'notes' => 'nullable|string|max:1000',
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

    // "Issue Parent Conference Slip" case action. The slip itself is handed
    // to the parent/guardian face-to-face to go over with them - it isn't
    // emailed or routed anywhere in-system - so this just records that GCU
    // issued one: when the conference is for, why, and any remarks. Each
    // issuance is its own immutable history row (same pattern as
    // Referral::feedbackSlips()), so a case can have more than one over
    // time without overwriting the previous record.
    public function issueParentConferenceSlip(Request $request, CaseFile $case)
    {
        $this->authorizeStaffAccess();
        $this->authorizeCaseWriter();

        $validated = $request->validate([
            'conference_date' => 'required|date|after_or_equal:today',
            'conference_time' => 'required',
            'reason'          => ['required', 'string', 'max:255', 'regex:' . self::TEXT_REGEX],
            'remarks'         => 'nullable|string|max:1000',
        ]);

        $slip = $case->parentConferenceSlips()->create([
            ...$validated,
            'issued_by_user_id' => $request->user()->id,
            'issued_at'         => now(),
        ]);

        AuditLog::record('parent_conference_slip_issued', "Parent Conference Slip issued for case {$case->case_number}.", $case);

        // The student has to be told about it. The slip is already saved by
        // now, so a notification failure must not turn the request into an
        // error - but it is logged so it can actually be diagnosed.
        $student = $case->student ?? \App\Models\Student::find($case->student_id);
        if ($student) {
            try {
                Notification::send($student, new ParentConferenceSlipNotification($slip));
            } catch (\Throwable $e) {
                \Log::error('Parent conference slip notification failed: ' . $e->getMessage(), ['slip_id' => $slip->id]);
            }
        }

        return response()->json($slip->load('issuedBy'));
    }

    // Parent Conference notes are written once (like follow-up notes) and are
    // read-only afterwards; they also can't be added to a closed SIF.
    public function saveParentConferenceNotes(Request $request, \App\Models\ParentConferenceSlip $slip)
    {
        $this->authorizeStaffAccess();
        $this->authorizeCaseWriter();

        $validated = $request->validate([
            'notes' => 'required|string|max:3000',
        ]);

        if (trim((string) $slip->notes) !== '') {
            abort(422, 'Parent conference notes were already saved and can no longer be edited.');
        }

        $case = $slip->caseFile;
        $case?->referrals()->latest()->first()?->abortIfLocked();

        $slip->update([
            'notes'                     => trim($validated['notes']),
            'notes_recorded_by_user_id' => $request->user()->id,
            'notes_recorded_at'         => now(),
        ]);

        AuditLog::record('parent_conference_notes_saved', "Parent Conference notes recorded for case {$case?->case_number}.", $case);

        return response()->json($slip->load(['issuedBy', 'notesRecordedBy']));
    }
}
