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
            ->when($request->search, fn($q) => $q->where(fn($sq) =>
                $sq->where('first_name', 'like', "%{$request->search}%")
                   ->orWhere('last_name', 'like', "%{$request->search}%")
                   ->orWhere('student_id', 'like', "%{$request->search}%")
            ))
            ->when($request->college, fn($q) => $q->where('college', $request->college))
            ->when($request->year_level, fn($q) => $q->where('year_level', $request->year_level))
            ->when($request->has('is_active'), fn($q) => $q->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN)));

        return response()->json($query->latest()->paginate($request->per_page ?? 10));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
        'student_id'             => 'required|string|unique:students,student_id',
        'first_name'             => 'required|string|max:255',
        'last_name'              => 'required|string|max:255',
        'middle_name'            => 'nullable|string|max:255',
        'sex'                    => 'nullable|in:Male,Female,Prefer not to say',
        'email'                  => 'nullable|email',
        'contact_number'         => 'nullable|string|max:11',
        'college'                => 'nullable|string',
        'program'                => 'nullable|string',
        'year_level'             => 'nullable|string',
        'section'                => 'nullable|string|max:1',
        'guardian_first_name'    => 'nullable|string|max:255',
        'guardian_middle_name'   => 'nullable|string|max:255',
        'guardian_last_name'     => 'nullable|string|max:255',
        'guardian_contact'       => 'nullable|string|max:11',
        'guardian_relationship'  => 'nullable|string',
    ]);

        $student = Student::create([
            ...$validated,
            'is_active' => true,
        ]);

        AuditLog::record('created', "Added student profile for {$student->first_name} {$student->last_name}.", $student);

        return response()->json($student, 201);
    }

    public function show(Student $student)
    {
        return response()->json($student);
    }

    public function update(Request $request, Student $student)
    {
        $validated = $request->validate([
            'student_id'             => 'sometimes|string|unique:students,student_id,' . $student->id,
            'first_name'             => 'sometimes|string|max:255',
            'last_name'              => 'sometimes|string|max:255',
            'middle_name'            => 'nullable|string|max:255',
            'suffix'                 => 'nullable|string|max:20',
            'sex'                    => 'nullable|in:Male,Female,Prefer not to say',
            'email'                  => 'nullable|email',
            'contact_number'         => 'nullable|string|max:11',
            'college'                => 'nullable|string',
            'program'                => 'nullable|string',
            'year_level'             => 'nullable|string',
            'section'                => 'nullable|string|max:1',
            'guardian_name'          => 'nullable|string|max:255',
            'guardian_contact'       => 'nullable|string|max:11',
            'guardian_relationship'  => 'nullable|string',
        ]);

        $old = $student->toArray();
        $student->update($validated);

        AuditLog::record('updated', "Updated student profile for {$student->first_name} {$student->last_name}.", $student, $old, $student->toArray());

        return response()->json($student);
    }

    public function destroy(Student $student)
    {
        $student->delete();
        AuditLog::record('deleted', "Deleted student profile for {$student->first_name} {$student->last_name}.");
        return response()->json(['message' => 'Student deleted.']);
    }

    public function toggleActive(Student $student)
    {
        $student->update(['is_active' => !$student->is_active]);
        AuditLog::record('toggled', "Student {$student->first_name} {$student->last_name} " . ($student->is_active ? 'activated' : 'deactivated') . ".", $student);
        return response()->json($student);
    }

    public function graduate(Request $request, Student $student)
    {
        $openCases = $student->cases()->whereNotIn('status', ['closed', 'resolved'])->count();
        if ($openCases > 0) {
            return response()->json(['message' => "Cannot mark as graduated: student has {$openCases} open case(s)."], 422);
        }
        $student->update(['is_active' => false]);
        AuditLog::record('graduated', "Marked student {$student->student_id} as graduated/inactive.", $student);
        return response()->json($student);
    }

    public function history(Student $student)
    {
        return response()->json([
            'cases'        => $student->cases()->with('counselor')->latest()->get(),
            'referrals'    => $student->referrals()->latest()->get(),
            'appointments' => $student->appointments()->with('staff')->latest()->get(),
        ]);
    }

    // ==========================
    // Bulk Import (direct, no preview)
    // ==========================
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx,xls',
        ]);

        $file = $request->file('file');
        $ext  = strtolower($file->getClientOriginalExtension());
        $headerMap = $this->studentHeaderMap();
        $rows = $this->parseFile($file, $ext, $headerMap);

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
                'student_id'             => $rowData['student_id'],
                'first_name'             => $rowData['first_name'],
                'last_name'              => $rowData['last_name'],
                'middle_name'            => $rowData['middle_name'] ?? null,
                'sex'                    => $rowData['sex'] ?? null,
                'email'                  => $rowData['email'] ?? null,
                'contact_number'         => $rowData['contact_number'] ?? null,
                'college'                => $rowData['college'] ?? null,
                'program'                => $rowData['program'] ?? null,
                'year_level'             => $rowData['year_level'] ?? null,
                'section'                => $rowData['section'] ?? null,
                'guardian_name'          => $rowData['guardian_name'] ?? null,
                'guardian_contact'       => $rowData['guardian_contact'] ?? null,
                'guardian_relationship'  => $rowData['guardian_relationship'] ?? null,
                'is_active'              => true,
            ]);
            $created++;
        }

        AuditLog::record('imported', "Bulk imported {$created} students, skipped {$skipped}.");

        return response()->json([
            'created' => $created,
            'skipped' => $skipped,
            'errors'  => $errors,
        ]);
    }

    // Bulk Import with Preview + Duplicate handling
    public function importPreview(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx,xls',
        ]);

        $file = $request->file('file');
        $ext  = strtolower($file->getClientOriginalExtension());
        $headerMap = $this->studentHeaderMap();
        $rows = $this->parseFile($file, $ext, $headerMap);

        $preview = [];
        foreach ($rows as $i => $rowData) {
            $isDuplicate = !empty($rowData['student_id']) && Student::where('student_id', $rowData['student_id'])->exists();
            $preview[] = [
                'row'          => $i + 2,
                'student_id'   => $rowData['student_id'] ?? null,
                'is_duplicate' => $isDuplicate,
                'valid'        => !empty($rowData['student_id']) && !empty($rowData['first_name']) && !empty($rowData['last_name']),
            ];
        }

        $token = uniqid('import_');
        cache()->put("import_preview_{$token}", $rows, now()->addMinutes(15));

        return response()->json([
            'token'      => $token,
            'preview'    => $preview,
            'total'      => count($preview),
            'duplicates' => collect($preview)->where('is_duplicate', true)->count(),
        ]);
    }

    public function importConfirm(Request $request)
    {
        $request->validate([
            'token'     => 'required|string',
            'decisions' => 'required|array',
        ]);

        $rows = cache()->get("import_preview_{$request->token}");
        if (!$rows) {
            return response()->json(['message' => 'Import session expired. Please re-upload the file.'], 422);
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors  = [];

        foreach ($rows as $i => $rowData) {
            $decision = $request->decisions[$i] ?? 'create';

            if ($decision === 'skip') {
                $skipped++;
                continue;
            }

            if (empty($rowData['student_id']) || empty($rowData['first_name']) || empty($rowData['last_name'])) {
                $errors[] = "Row " . ($i + 2) . ": missing required fields.";
                $skipped++;
                continue;
            }

            $existing = Student::where('student_id', $rowData['student_id'])->first();

            if ($existing && $decision === 'update') {
                $existing->update([
                    'first_name'             => $rowData['first_name'],
                    'last_name'              => $rowData['last_name'],
                    'middle_name'            => $rowData['middle_name'] ?? $existing->middle_name,
                    'sex'                    => $rowData['sex'] ?? $existing->sex,
                    'email'                  => $rowData['email'] ?? $existing->email,
                    'contact_number'         => $rowData['contact_number'] ?? $existing->contact_number,
                    'college'                => $rowData['college'] ?? $existing->college,
                    'program'                => $rowData['program'] ?? $existing->program,
                    'year_level'             => $rowData['year_level'] ?? $existing->year_level,
                    'section'                => $rowData['section'] ?? $existing->section,
                    'guardian_name'          => $rowData['guardian_name'] ?? $existing->guardian_name,
                    'guardian_contact'       => $rowData['guardian_contact'] ?? $existing->guardian_contact,
                    'guardian_relationship'  => $rowData['guardian_relationship'] ?? $existing->guardian_relationship,
                ]);
                $updated++;
                continue;
            }

            if ($existing) {
                $skipped++;
                continue;
            }

            Student::create([
                'student_id'             => $rowData['student_id'],
                'first_name'             => $rowData['first_name'],
                'last_name'              => $rowData['last_name'],
                'middle_name'            => $rowData['middle_name'] ?? null,
                'sex'                    => $rowData['sex'] ?? null,
                'email'                  => $rowData['email'] ?? null,
                'contact_number'         => $rowData['contact_number'] ?? null,
                'college'                => $rowData['college'] ?? null,
                'program'                => $rowData['program'] ?? null,
                'year_level'             => $rowData['year_level'] ?? null,
                'section'                => $rowData['section'] ?? null,
                'guardian_name'          => $rowData['guardian_name'] ?? null,
                'guardian_contact'       => $rowData['guardian_contact'] ?? null,
                'guardian_relationship'  => $rowData['guardian_relationship'] ?? null,
                'is_active'              => true,
            ]);
            $created++;
        }

        cache()->forget("import_preview_{$request->token}");
        AuditLog::record('imported', "Bulk imported students: {$created} created, {$updated} updated, {$skipped} skipped.");

        return response()->json([
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors'  => $errors,
        ]);
    }

    private function studentHeaderMap(): array
    {
        return [
            'student id'              => 'student_id',
            'last name'               => 'last_name',
            'first name'              => 'first_name',
            'middle name'             => 'middle_name',
            'sex'                     => 'sex',
            'email address'           => 'email',
            'email'                   => 'email',
            'contact number'          => 'contact_number',
            'college'                 => 'college',
            'program'                 => 'program',
            'year level'              => 'year_level',
            'section'                 => 'section',
            'guardian name'           => 'guardian_name',
            'guardian contact'        => 'guardian_contact',
            'guardian relationship'   => 'guardian_relationship',
        ];
    }

    private function parseFile($file, $ext, $headerMap)
    {
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

        return $rows;
    }
    public function checkDuplicateName(Request $request)
{
    $request->validate([
        'first_name' => 'required|string',
        'last_name'  => 'required|string',
    ]);

    $existing = Student::where('first_name', $request->first_name)
        ->where('last_name', $request->last_name)
        ->when($request->middle_name, fn($q) => $q->where('middle_name', $request->middle_name))
        ->first();

    return response()->json([
        'duplicate_found' => (bool) $existing,
        'existing_student' => $existing,
    ]);
}
}