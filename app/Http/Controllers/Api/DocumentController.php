<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentController extends Controller
{
    public function upload(Request $request)
    {
        $request->validate([
            // Whitelist by real content (not the client-supplied extension)
            'file'              => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx|mimetypes:application/pdf,image/jpeg,image/png,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/zip,application/octet-stream',
            'document_type'     => 'required|in:referral_slip,client_information_form,psychological_assessment_report,session_notes,admission_slip,incident_report,supporting_document,other',
            'documentable_type' => 'required|in:referral,case,testing_record,complaint',
            'documentable_id'   => 'required|integer',
            'description'       => 'nullable|string',
            'is_confidential'   => 'boolean',
        ]);

        // The client only picks a short key; the real model class comes from this map,
        // so nobody can attach documents to arbitrary classes.
        $typeMap = [
            'referral'       => \App\Models\Referral::class,
            'case'           => \App\Models\CaseFile::class,
            'testing_record' => \App\Models\TestingRecord::class,
            'complaint'      => \App\Models\Complaint::class,
        ];
        $modelClass = $typeMap[$request->documentable_type];
        abort_unless($modelClass::whereKey($request->documentable_id)->exists(), 422, 'The record you are attaching to does not exist.');

        $file = $request->file('file');
        // Extension comes from the detected content type, never from the client's filename.
        $ext = $file->guessExtension() ?: 'bin';
        $storedFilename = Str::uuid() . '.' . $ext;
        $path = $file->storeAs('documents', $storedFilename, 'private');

        $document = Document::create([
            'uploaded_by_user_id' => $request->user()->id,
            'documentable_type'   => $modelClass,
            'documentable_id'     => $request->documentable_id,
            'original_filename'   => $file->getClientOriginalName(),
            'stored_filename'     => $storedFilename,
            'disk'                => 'private',
            'path'                => $path,
            'mime_type'           => $file->getMimeType(),
            'file_size'           => $file->getSize(),
            'document_type'       => $request->document_type,
            'description'         => $request->description,
            'is_confidential'     => $request->is_confidential ?? false,
        ]);

        AuditLog::record('uploaded', "Uploaded document {$document->original_filename}.", $document);
        return response()->json($document, 201);
    }

    // Confidential files (e.g. the PAR) are for GCU / TMDU only - SDU can't open them.
    private function authorizeAccess(Request $request, Document $document): void
    {
        if ($document->is_confidential && !in_array($request->user()->role, ['admin', 'gcu_staff', 'tmdu_staff'], true)) {
            abort(403, 'You do not have access to this confidential document.');
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
        if (!Storage::disk($document->disk)->exists($document->path)) {
            return response()->json(['message' => 'File not found.'], 404);
        }

        AuditLog::record('downloaded', "Downloaded document {$document->original_filename}.", $document);
        return Storage::disk($document->disk)->download($document->path, $document->original_filename, [
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function destroy(Request $request, Document $document)
    {
        // Only the uploader or the GCU Head may delete a document.
        abort_unless(
            $request->user()->role === 'admin' || $document->uploaded_by_user_id === $request->user()->id,
            403,
            'You can only delete documents you uploaded.'
        );

        Storage::disk($document->disk)->delete($document->path);
        AuditLog::record('deleted', "Deleted document {$document->original_filename}.", $document);
        $document->delete();
        return response()->json(['message' => 'Document deleted.']);
    }
}