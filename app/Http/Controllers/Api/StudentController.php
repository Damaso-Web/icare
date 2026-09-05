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
        'file' => 'required|file|mimes:csv,txt,xlsx,xls',
    ]);

    $file = $request->file('file');
    $ext  = strtolower($file->getClientOriginalExtension());

    // Map friendly headers to database columns
    $headerMap = [
        'student id'      => 'student_id',
        'last name'       => 'last_name',
        'first name'      => 'first_name',
        'middle name'     => 'middle_name',
        'sex'             => 'sex',
        'email address'   => 'email',
        'email'           => 'email',
        'contact number'  => 'contact_number',
        'college'         => 'college',
        'program'         => 'program',
        'year level'      => 'year_level',
        'section'         => 'section',
    ];

    $rows = [];

    if (in_array($ext, ['xlsx', 'xls'])) {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();
        $data  = $sheet->toArray(null, true, true, false);

        $rawHeader = array_map(fn($h) => strtolower(trim($h ?? '')), $data[0]);
        for ($i = 1; $i < count($data); $i++) {
            $rowAssoc = [];
            foreach ($rawHeader as $idx => $key) {
                $mappedKey = $headerMap[$key] ?? $key;
                $rowAssoc[$mappedKey] = $data[$i][$idx] ?? null;
            }
            $rows[] = $rowAssoc;
        }
    } else {
        $handle = fopen($file->getRealPath(), 'r');
        $rawHeader = array_map(fn($h) => strtolower(trim($h)), fgetcsv($handle));
        while (($data = fgetcsv($handle)) !== false) {
            $rowAssoc = [];
            foreach ($rawHeader as $idx => $key) {
                $mappedKey = $headerMap[$key] ?? $key;
                $rowAssoc[$mappedKey] = $data[$idx] ?? null;
            }
            $rows[] = $rowAssoc;
        }
        fclose($handle);
    }

    $created = 0;
    $skipped = 0;
    $errors  = [];
    $rowNum  = 1;

    foreach ($rows as $rowData) {
        $rowNum++;

        if (empty($rowData['student_id']) || empty($rowData['first_name']) || empty($rowData['last_name'])) {
            $errors[] = "Row {$rowNum}: missing required fields (Student ID, First Name, Last Name).";
            $skipped++;
            continue;
        }

        $exists = Student::where('student_id', $rowData['student_id'])->exists();
        if ($exists) {
            $errors[] = "Row {$rowNum}: Student ID {$rowData['student_id']} already exists — skipped.";
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