<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Referral;
use Illuminate\Http\Request;

// GCU's read-only view of the Case Referral Slips (the "Refer to TMDU" forms)
// it has filled out and sent to TMDU: the form as submitted, where it stands
// in TMDU's testing workflow, and the PAR once it has been released.
// TMDU owns the slip itself - nothing here can change it.
class CaseReferralController extends Controller
{
    // Bug (10/24/2026, B624): a gcu_staff counselor could see every
    // student's TMDU testing results, not just their own caseload - only
    // the GCU Head (admin) is meant to have unit-wide visibility here,
    // mirroring the same scoping CaseController now applies to Student
    // Information Files (B623). Scoped by the underlying case's assigned
    // counselor, same as everywhere else a case is scoped.
    private function authorizeOwnCaseload(?Referral $referral): void
    {
        $user = request()->user();
        if ($user->role !== 'gcu_staff' || !$referral) {
            return;
        }
        if ((int) ($referral->case?->primary_counselor_id) !== (int) $user->id) {
            abort(403, 'Unauthorized. This student is not in your assigned caseload.');
        }
    }

    private function payload(Referral $referral): array
    {
        $record = $referral->testingRecord;
        $par = $record?->documents
            ?->where('document_type', 'psychological_assessment_report')
            ->sortByDesc('id')
            ->first();

        return [
            'id'                => $referral->id,
            'referral_code'     => $referral->referral_code,
            'status'            => $referral->status,
            'created_at'        => $referral->created_at,
            'acknowledged_at'   => $referral->acknowledged_at,
            'reason'            => $referral->nature_of_concern,
            'referrer_name'     => $referral->referrer_name ?? $referral->referredBy?->name,
            'student'           => $referral->student?->only([
                'id', 'student_id', 'first_name', 'middle_name', 'last_name', 'suffix',
                'college', 'program', 'year_level', 'section',
            ]),
            'case_number'       => $referral->case?->case_number,
            'source_referral'   => $record?->sourceReferral?->only(['id', 'referral_code', 'referral_type']),
            'testing_record'    => $record ? [
                'id'                 => $record->id,
                'status'             => $record->status,
                'tester'             => $record->tester?->only(['id', 'name']),
                'testing_date'       => $record->testing_date,
                'tests_administered' => $record->tests_administered,
                'assessment_summary' => $record->assessment_summary,
                'recommendations'    => $record->recommendations,
                'report_sent_at'     => $record->report_sent_at,
                'par_document'       => $par ? $par->only(['id', 'original_filename']) : null,
            ] : null,
        ];
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $query = Referral::tmduOwned()
            ->with(['student', 'referredBy', 'case', 'testingRecord.tester', 'testingRecord.documents', 'testingRecord.sourceReferral'])
            ->when($user->role === 'gcu_staff', fn($q) => $q->whereHas('case', fn($c) => $c->where('primary_counselor_id', $user->id)))
            ->when($request->status, fn($q) => $q->whereHas('testingRecord', fn($t) => $t->where('status', $request->status)))
            ->when($request->search, fn($q) => $q->where(function ($qq) use ($request) {
                $s = $request->search;
                $qq->where('referral_code', 'like', "%{$s}%")
                   ->orWhereHas('student', fn($st) => $st
                        ->where('first_name', 'like', "%{$s}%")
                        ->orWhere('last_name', 'like', "%{$s}%")
                        ->orWhere('student_id', 'like', "%{$s}%"));
            }))
            ->latest();

        $page = $query->paginate(min(max((int) $request->input('per_page', 20), 1), 200));
        $page->setCollection($page->getCollection()->map(fn($r) => $this->payload($r)));

        return response()->json($page);
    }

    public function show(Referral $referral)
    {
        abort_unless($referral->isTmduOwned(), 404);
        $this->authorizeOwnCaseload($referral->loadMissing('case'));

        $referral->load(['student', 'referredBy', 'case', 'testingRecord.tester', 'testingRecord.documents', 'testingRecord.sourceReferral']);
        AuditLog::record('viewed', "Viewed Case Referral {$referral->referral_code}.", $referral);

        return response()->json($this->payload($referral));
    }

    // The Case Referral Slip created from a given GCU referral (null if that
    // referral hasn't been referred to TMDU) - used by the SIF's View button.
    public function forSource(Referral $referral)
    {
        $this->authorizeOwnCaseload($referral->loadMissing('case'));

        $slip = Referral::tmduOwned()
            ->whereHas('testingRecord', fn($t) => $t->where('source_referral_id', $referral->id))
            ->with(['student', 'referredBy', 'case', 'testingRecord.tester', 'testingRecord.documents', 'testingRecord.sourceReferral'])
            ->latest()
            ->first();

        // json(null) is sent as "{}", which the page read as "a slip exists" and so
        // showed View Case Referral on every referral. Say it outright instead.
        return $slip
            ? response()->json($this->payload($slip))
            : response('null', 200, ['Content-Type' => 'application/json']);
    }
}