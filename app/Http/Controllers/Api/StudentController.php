<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Student;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    public function index(Request $request)
    {
        $query = Student::query()
            ->when($request->search, fn($q) =>
                $q->where('first_name', 'like', "%{$request->search}%")
                  ->orWhere('last_name', 'like', "%{$request->search}%")
                  ->orWhere('student_id', 'like', "%{$request->search}%")
            )
            ->when($request->college,    fn($q) => $q->where('college', $request->college))
            ->when($request->year_level, fn($q) => $q->where('year_level', $request->year_level));

        return response()->json($query->latest()->paginate(20));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'student_id'           => 'required|string|unique:students,student_id',
            'first_name'           => 'required|string',
            'last_name'            => 'required|string',
            'middle_name'          => 'nullable|string',
            'email'                => 'nullable|email',
            'contact_number'       => 'nullable|string',
            'sex'                  => 'nullable|in:Male,Female,Prefer not to say',
            'birthdate'            => 'nullable|date',
            'year_level'           => 'required|string',
            'college'              => 'required|string',
            'program'              => 'nullable|string',
            'section'              => 'nullable|string',
            'address'              => 'nullable|string',
            'guardian_name'        => 'nullable|string',
            'guardian_contact'     => 'nullable|string',
            'guardian_relationship'=> 'nullable|string',
            'medical_notes'        => 'nullable|string',
        ]);

        $student = Student::create($validated);
        AuditLog::record('created', "Created student profile for {$student->student_id}.", $student);
        return response()->json($student, 201);
    }

    public function show(Student $student)
    {
        AuditLog::record('viewed', "Viewed student profile {$student->student_id}.", $student);
        return response()->json($student->load(['referrals', 'cases', 'appointments']));
    }

    public function update(Request $request, Student $student)
    {
        $validated = $request->validate([
            'first_name'           => 'sometimes|string',
            'last_name'            => 'sometimes|string',
            'middle_name'          => 'nullable|string',
            'email'                => 'nullable|email',
            'contact_number'       => 'nullable|string',
            'sex'                  => 'nullable|in:Male,Female,Prefer not to say',
            'birthdate'            => 'nullable|date',
            'year_level'           => 'sometimes|string',
            'college'              => 'sometimes|string',
            'program'              => 'nullable|string',
            'section'              => 'nullable|string',
            'address'              => 'nullable|string',
            'guardian_name'        => 'nullable|string',
            'guardian_contact'     => 'nullable|string',
            'guardian_relationship'=> 'nullable|string',
            'medical_notes'        => 'nullable|string',
        ]);

        $old = $student->toArray();
        $student->update($validated);
        AuditLog::record('updated', "Updated student profile {$student->student_id}.", $student, $old, $student->toArray());
        return response()->json($student);
    }

    public function destroy(Student $student)
    {
        AuditLog::record('deleted', "Deleted student profile {$student->student_id}.", $student);
        $student->delete();
        return response()->json(['message' => 'Student deleted.']);
    }

    public function history(Student $student)
    {
        AuditLog::record('viewed', "Viewed full history for student {$student->student_id}.", $student);
        return response()->json([
            'student'      => $student,
            'referrals'    => $student->referrals()->with('referredBy')->get(),
            'cases'        => $student->cases()->with('counselor')->get(),
            'appointments' => $student->appointments()->with('staff')->get(),
            'sessions'     => $student->sessionNotes()->with('recordedBy')->get(),
            'testing'      => $student->testingRecords()->with('tester')->get(),
        ]);
    }

    public function cases(Student $student)
    {
        return response()->json($student->cases()->with(['counselor', 'sessionNotes'])->get());
    }
    
    public function toggleActive(Student $student)
{
    $student->update(['is_active' => !$student->is_active]);
    \App\Models\AuditLog::record('toggled', "User {$student->first_name} {$student->last_name} " . ($student->is_active ? 'activated' : 'deactivated') . ".", $student);
    return response()->json($student);
}
public function import(Request $request)
{
    $request->validate([
        'file' => 'required|file|mimes:csv,txt',
    ]);

    $path = $request->file('file')->getRealPath();
    $handle = fopen($path, 'r');
    $header = fgetcsv($handle);
    $header = array_map(fn($h) => strtolower(trim($h)), $header);

    $required = ['student_id', 'first_name', 'last_name'];
    foreach ($required as $col) {
        if (!in_array($col, $header)) {
            fclose($handle);
            return response()->json(['message' => "Missing required column: {$col}"], 422);
        }
    }

    $created = 0;
    $skipped = 0;
    $errors  = [];
    $row = 1;

    while (($data = fgetcsv($handle)) !== false) {
        $row++;
        $rowData = array_combine($header, $data);

        if (empty($rowData['student_id']) || empty($rowData['first_name']) || empty($rowData['last_name'])) {
            $errors[] = "Row {$row}: missing required fields.";
            $skipped++;
            continue;
        }

        $exists = Student::where('student_id', $rowData['student_id'])->exists();
        if ($exists) {
            $skipped++;
            continue;
        }

        Student::create([
            'student_id'     => $rowData['student_id'],
            'first_name'     => $rowData['first_name'],
            'last_name'      => $rowData['last_name'],
            'middle_name'    => $rowData['middle_name'] ?? null,
            'sex'            => $rowData['sex'] ?? null,
            'email'          => $rowData['email'] ?? null,
            'contact_number' => $rowData['contact_number'] ?? null,
            'college'        => $rowData['college'] ?? null,
            'program'        => $rowData['program'] ?? null,
            'year_level'     => $rowData['year_level'] ?? null,
            'section'        => $rowData['section'] ?? null,
            'is_active'      => true,
        ]);
        $created++;
    }

    fclose($handle);

    \App\Models\AuditLog::record('imported', "Bulk imported {$created} students, skipped {$skipped}.");

    return response()->json([
        'created' => $created,
        'skipped' => $skipped,
        'errors'  => $errors,
    ]);
}

public function graduate(Request $request, Student $student)
{
    $openCases = $student->cases()->whereNotIn('status', ['closed', 'resolved'])->count();
    if ($openCases > 0) {
        return response()->json(['message' => "Cannot mark as graduated: student has {$openCases} open case(s)."], 422);
    }
    $student->update(['is_active' => false]);
    \App\Models\AuditLog::record('graduated', "Marked student {$student->student_id} as graduated/inactive.", $student);
    return response()->json($student);
}
}