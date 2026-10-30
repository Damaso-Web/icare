<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CaseFile;
use App\Models\Complaint;
use App\Models\Document;
use App\Models\Referral;
use App\Models\TestingRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    public function upload(Request $request)
    {
        $request->validate([
            'file'              => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx|mimetypes:application/pdf,image/jpeg,image/png,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/zip,application/octet-stream',
            'document_type'     => 'required|in:referral_slip,client_information_form,psychological_assessment_report,session_notes,admission_slip,incident_report,supporting_document,other',
            'documentable_type' => 'required|in:referral,case,testing_record,complaint',
            'documentable_id'   => 'required|integer',
            'description'       => 'nullable|string',
            'is_confidential'   => 'boolean',
        ]);

        $typeMap = [
            'referral'       => \App\Models\Referral::class,
            'case'           => \App\Models\CaseFile::class,
            'testing_record' => \App\Models\TestingRecord::class,
            'complaint'      => \App\Models\Complaint::class,
        ];
        $modelClass = $typeMap[$request->documentable_type];
        $parent = $modelClass::find($request->documentable_id);
        abort_unless($parent, 422, 'The record you are attaching to does not exist.');

        // B642 (10/31/2026): previously the ONLY check on upload was "the
        // record exists" - an SDU Head could blind-write a document onto a
        // TMDU case and then never see it again (show/download would 403),
        // leaving orphaned rows in the DB and files in Cloudinary. Same
        // unit policy the show/download routes already use, applied before
        // the file is ever uploaded so a denied request has no side effect.
        $this->authorizeParent($request, $parent, $modelClass);

        // A non-privileged uploader must not be able to flag a document as
        // confidential if they couldn't otherwise see one - otherwise they
        // could make their own upload unreadable to themselves later.
        $canSetConfidential = in_array($request->user()->role, ['admin', 'gcu_staff', 'tmdu_staff', 'tmdu_head'], true);
        $isConfidential = $canSetConfidential && (bool) ($request->is_confidential ?? false);

        $file = $request->file('file');

        try {
            $document = Document::storeUploadedFile($file, [
                'uploaded_by_user_id' => $request->user()->id,
                'documentable_type'   => $modelClass,
                'documentable_id'     => $request->documentable_id,
                'original_filename'   => $file->getClientOriginalName(),
                'document_type'       => $request->document_type,
                'description'         => $request->description,
                'is_confidential'     => $isConfidential,
            ]);
        } catch (\Throwable $e) {
            Log::error('Document upload to Cloudinary failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'The file could not be uploaded right now. Please try again in a moment.'], 502);
        }

        AuditLog::record('uploaded', "Uploaded document {$document->original_filename}.", $document);
        return response()->json($document, 201);
    }

    // Confidential files (e.g. the PAR) are for GCU / TMDU only - SDU can't open them.
    //
    // Bug (10/29/2026): this was the ONLY check here. A non-confidential
    // document attached to, say, a TMDU case was reachable by anyone in
    // ['admin','gcu_staff','sdu_head','tmdu_staff','tmdu_head'] (the role
    // list the documents/* routes allow), with no check on which unit the
    // document's own case/referral/testing record/complaint belongs to -
    // so an SDU Head could view and download a document that had nothing
    // to do with SDU just because it wasn't marked confidential. Every
    // other controller in the app enforces a per-unit check on top of the
    // role list (CaseController::authorizeUnitAccess(),
    // ReferralController::authorizeView(), the testing-records route
    // group, ComplaintController::attachment()) - documents were the one
    // place that check was missing. This reuses those same checks instead
    // of reinventing unit logic here, so there's exactly one source of
    // truth for "does this unit own this record."
    private function authorizeAccess(Request $request, Document $document): void
    {
        $user = $request->user();

        if ($document->is_confidential && !in_array($user->role, ['admin', 'gcu_staff', 'tmdu_staff', 'tmdu_head'], true)) {
            abort(403, 'You do not have access to this confidential document.');
        }

        if ($document->documentable) {
            $this->authorizeParent($request, $document->documentable, $document->documentable_type);
        }
    }

    /**
     * B642: shared unit-scope check for "does this user own/see this record"
     * - applied to the parent record on upload, show, download and destroy
     * so no route can bypass it.
     */
    private function authorizeParent(Request $request, $parent, string $parentType): void
    {
        $user = $request->user();

        // Admin and GCU staff are unit-wide ("team model") everywhere else
        // in the app - no per-unit narrowing applies to them here either.
        if ($user->isAdmin() || $user->isGCUStaff()) {
            return;
        }

        switch ($parentType) {
            case CaseFile::class:
                if ($user->isTMDUStaff() && $parent->current_unit !== 'TMDU') {
                    abort(403, 'This document belongs to a different unit.');
                }
                if ($user->isSDUHead() && $parent->current_unit !== 'SDU') {
                    abort(403, 'This document belongs to a different unit.');
                }
                break;

            case Referral::class:
                app(ReferralController::class)->authorizeView($parent, $user);
                break;

            case TestingRecord::class:
                // Testing Records belong to TMDU only - see the original comment.
                if (!$user->isTMDUStaff()) {
                    abort(403, 'This document belongs to a different unit.');
                }
                break;

            case Complaint::class:
                // Same rule as ComplaintController::attachment().
                if (!$user->isSDUHead() && (int) $parent->filed_by_user_id !== (int) $user->id) {
                    $referral = $parent->referral;
                    abort_unless($referral, 403, 'Unauthorized.');
                    app(ReferralController::class)->authorizeView($referral, $user);
                }
                break;
        }
    }

    public function show(Request $request, Document $document)
    {
        $this->authorizeAccess($request, $document);
        AuditLog::record('viewed', "Viewed document {$document->original_filename}.", $document);
        return response()->json($document->load('uploadedBy'));
    }

    public function download(Request $request, Document $document)
    {
        $this->authorizeAccess($request, $document);
        AuditLog::record('downloaded', "Downloaded document {$document->original_filename}.", $document);

        return $document->streamResponse();
    }

    public function destroy(Request $request, Document $document)
    {
        // B642: unit scope first - a cross-unit delete (e.g. an SDU Head's
        // legacy upload accidentally parked on a TMDU case) is refused
        // before the uploader-or-admin rule can allow it. Admin and
        // same-unit uploaders still pass; the original rule is otherwise
        // untouched.
        $this->authorizeAccess($request, $document);

        // Only the uploader or the GCU Head may delete a document.
        abort_unless(
            $request->user()->role === 'admin' || $document->uploaded_by_user_id === $request->user()->id,
            403,
            'You can only delete documents you uploaded.'
        );

        if ($document->disk === 'cloudinary' && $document->cloud_public_id) {
            app(\App\Support\CloudinaryClient::class)->delete($document->cloud_public_id, $document->cloud_resource_type ?: 'raw');
        } elseif ($document->disk && $document->path) {
            Storage::disk($document->disk)->delete($document->path);
        }

        AuditLog::record('deleted', "Deleted document {$document->original_filename}.", $document);
        $document->delete();
        return response()->json(['message' => 'Document deleted.']);
    }
}