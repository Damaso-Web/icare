<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\Student;
use Illuminate\Http\Request;

class ComplaintController extends Controller
{
    // Visible only to SDU Head (see routes/api.php) - kept deliberately
    // simple, no case linking, no urgency, no assignment.
    public function index(Request $request)
    {
        $query = Complaint::with(['complainee', 'filedBy'])
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->search, fn($q) => $q->whereHas('complainee', fn($s) =>
                $s->where('first_name', 'like', "%{$request->search}%")
                  ->orWhere('last_name', 'like', "%{$request->search}%")
                  ->orWhere('student_id', 'like', "%{$request->search}%")
            ));

        return response()->json($query->latest()->paginate(20));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'complainee_student_id' => 'required|exists:students,id',
            'violation_type'        => 'required|string|max:255',
            'description'           => 'required|string|min:10',
            'incident_date'         => 'nullable|date',
        ]);

        $student = Student::findOrFail($validated['complainee_student_id']);
        if (!$student->is_active) {
            return response()->json(['message' => 'This student account is deactivated and cannot be reported.'], 422);
        }

        $complaint = Complaint::create([
            ...$validated,
            'filed_by_user_id' => $request->user()->id,
            'status'            => 'pending',
        ]);

        return response()->json($complaint->load(['complainee', 'filedBy']), 201);
    }

    public function show(Complaint $complaint)
    {
        return response()->json($complaint->load(['complainee', 'filedBy']));
    }

    public function updateStatus(Request $request, Complaint $complaint)
    {
        $request->validate([
            'status' => 'required|in:pending,under_review,resolved',
        ]);
        $complaint->update(['status' => $request->status]);
        return response()->json($complaint);
    }
}