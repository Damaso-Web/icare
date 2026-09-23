<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\ComplaintAttachment;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ComplaintController extends Controller
{
    public function index(Request $request)
    {
        $query = Complaint::with(['complainee', 'filedBy'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('complaint_code', 'like', "%{$search}%")
                  ->orWhereHas('complainee', function ($sq) use ($search) {
                      $sq->where('first_name', 'like', "%{$search}%")
                         ->orWhere('last_name', 'like', "%{$search}%")
                         ->orWhere('student_id', 'like', "%{$search}%");
                  });
            });
        }

        return $query->paginate(20);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'complainant_name'       => 'required|string|max:255',
            'complainant_address'    => 'required|string|max:255',
            'complainee_student_id'  => 'required|exists:students,id',
            'complainee_position'    => 'nullable|string|max:255',
            'complainee_college'     => 'nullable|string|max:255',
            'complainee_department'  => 'nullable|string|max:255',
            'complainee_office'      => 'nullable|string|max:255',
            'complainee_address'     => 'nullable|string|max:255',
            'violation_type'         => 'required|string|max:255',
            'incident_date'          => 'required|date',
            'description'            => 'required|string',
            'certification_agreed'   => 'required|accepted',
            'evidence.*'             => 'nullable|file|max:10240',
            'affidavit.*'            => 'nullable|file|max:10240',
        ]);

        $student = Student::findOrFail($validated['complainee_student_id']);
        if (! $student->is_active) {
            return response()->json(['message' => 'This student record is inactive.'], 422);
        }

        $complaint = Complaint::create([
            ...$validated,
            'certification_agreed' => true,
            'filed_by_user_id'     => $request->user()->id,
            'status'                => 'pending',
        ]);

        foreach (['evidence', 'affidavit'] as $category) {
            foreach ($request->file($category, []) as $file) {
                $path = $file->store('complaints/' . $complaint->id, 'public');
                ComplaintAttachment::create([
                    'complaint_id'       => $complaint->id,
                    'category'           => $category,
                    'file_path'          => $path,
                    'original_filename'  => $file->getClientOriginalName(),
                ]);
            }
        }

        return $complaint->load(['complainee', 'filedBy', 'attachments']);
    }

    public function show(Complaint $complaint)
    {
        return $complaint->load(['complainee', 'filedBy', 'attachments']);
    }

    public function updateStatus(Request $request, Complaint $complaint)
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,under_review,resolved',
        ]);

        $complaint->update($validated);

        return $complaint->load(['complainee', 'filedBy', 'attachments']);
    }
}