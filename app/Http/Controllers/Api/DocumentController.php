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
            'file'              => 'required|file|max:10240',
            'document_type'     => 'required|in:referral_slip,client_information_form,psychological_assessment_report,session_notes,admission_slip,incident_report,supporting_document,other',
            'documentable_type' => 'required|string',
            'documentable_id'   => 'required|integer',
            'description'       => 'nullable|string',
            'is_confidential'   => 'boolean',
        ]);

        $file = $request->file('file');
        $storedFilename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('documents', $storedFilename, 'private');

        $document = Document::create([
            'uploaded_by_user_id' => $request->user()->id,
            'documentable_type'   => $request->documentable_type,
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

    public function show(Document $document)
    {
        AuditLog::record('viewed', "Viewed document {$document->original_filename}.", $document);
        return response()->json($document->load('uploadedBy'));
    }

    public function download(Document $document)
    {
        if (!Storage::disk($document->disk)->exists($document->path)) {
            return response()->json(['message' => 'File not found.'], 404);
        }

        AuditLog::record('downloaded', "Downloaded document {$document->original_filename}.", $document);
        return Storage::disk($document->disk)->download($document->path, $document->original_filename);
    }

    public function destroy(Document $document)
    {
        Storage::disk($document->disk)->delete($document->path);
        AuditLog::record('deleted', "Deleted document {$document->original_filename}.", $document);
        $document->delete();
        return response()->json(['message' => 'Document deleted.']);
    }
}