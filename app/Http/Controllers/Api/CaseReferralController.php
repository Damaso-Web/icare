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
        $query = Referral::tmduOwned()
            ->with(['student', 'referredBy', 'case', 'testingRecord.tester', 'testingRecord.documents', 'testingRecord.sourceReferral'])
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

        $page = $query->paginate(20);
        $page->setCollection($page->getCollection()->map(fn($r) => $this->payload($r)));

        return response()->json($page);
    }

    public function show(Referral $referral)
    {
        abort_unless($referral->isTmduOwned(), 404);

        $referral->load(['student', 'referredBy', 'case', 'testingRecord.tester', 'testingRecord.documents', 'testingRecord.sourceReferral']);
        AuditLog::record('viewed', "Viewed Case Referral {$referral->referral_code}.", $referral);

        return response()->json($this->payload($referral));
    }

    // The Case Referral Slip created from a given GCU referral (null if that
    // referral hasn't been referred to TMDU) - used by the SIF's View button.
    public function forSource(Referral $referral)
    {
        $slip = Referral::tmduOwned()
            ->whereHas('testingRecord', fn($t) => $t->where('source_referral_id', $referral->id))
            ->with(['student', 'referredBy', 'case', 'testingRecord.tester', 'testingRecord.documents', 'testingRecord.sourceReferral'])
            ->latest()
            ->first();

        return response()->json($slip ? $this->payload($slip) : null);
    }
}