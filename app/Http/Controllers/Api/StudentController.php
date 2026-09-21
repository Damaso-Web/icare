<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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

        $sortBy  = in_array($request->sort_by, ['created_at', 'student_id', 'last_name']) ? $request->sort_by : 'created_at';
        $sortDir = $request->sort_dir === 'asc' ? 'asc' : 'desc';
        if ($sortBy === 'last_name') {
            $query->orderBy('last_name', $sortDir)->orderBy('first_name', $sortDir);
        } else {
            $query->orderBy($sortBy, $sortDir);
        }

        return response()->json($query->paginate($request->per_page ?? 10));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'student_id'             => 'required|string|unique:students,student_id',
            'first_name'             => 'required|string|max:255',
            'last_name'              => 'required|string|max:255',
            'middle_name'            => 'nullable|string|max:255',
            'suffix'                 => 'nullable|string|max:20',
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

        $tempPassword = Str::random(10);

        $student = Student::create([
            ...$validated,
            'password'              => Hash::make($tempPassword),
            'temp_password'         => $tempPassword,
            'must_change_password'  => true,
            'is_active'             => true,
        ]);

        AuditLog::record('created', "Added student profile for {$student->first_name} {$student->last_name}.", $student);

        return response()->json([
            ...$student->toArray(),
            'temp_password' => $tempPassword,
        ], 201);
    }

    public function show(Student $student)
    {
        return response()->json($student);
    }

    public function update(Request $request, Student $student)
    {
        $validated = $request->validate([
            'student_id'             => 'sometimes|required|string|unique:students,student_id,' . $student->id,
            'first_name'             => 'sometimes|required|string|max:255',
            'last_name'              => 'sometimes|required|string|max:255',
            'middle_name'            => 'nullable|string|max:255',
            'suffix'                 => 'nullable|string|max:20',
            'sex'                    => 'nullable|in:Male,Female,Prefer not to say',
            'email'                  => 'nullable|email',
            'contact_number'         => 'nullable|string|max:11',
            'college'                => 'sometimes|required|string',
            'program'                => 'nullable|string',
            'year_level'             => 'sometimes|required|string',
            'section'                => 'nullable|string|max:1',
            'guardian_first_name'    => 'nullable|string|max:255',
            'guardian_middle_name'   => 'nullable|string|max:255',
            'guardian_last_name'     => 'nullable|string|max:255',
            'guardian_contact'       => 'nullable|string|max:11',
            'guardian_relationship'  => 'nullable|string',
        ]);

        // Nullable fields only ever mean "leave as-is" when submitted blank - an
        // edit form re-sends the whole record, so an empty value here is never a
        // deliberate clear, just a field the admin didn't touch.
        foreach ([
            'middle_name', 'suffix', 'sex', 'email', 'contact_number',
            'college', 'program', 'year_level', 'section',
            'guardian_first_name', 'guardian_middle_name', 'guardian_last_name',
            'guardian_contact', 'guardian_relationship',
        ] as $optionalField) {
            if (array_key_exists($optionalField, $validated) && $validated[$optionalField] === '') {
                unset($validated[$optionalField]);
            }
        }

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
        $validated = $request->validate([
            'deactivation_reason' => 'required|in:no_longer_enrolled,leave_of_absence,disciplinary_suspension,other',
            'deactivation_notes'  => 'nullable|string|required_if:deactivation_reason,other',
        ]);

        $openCases = $student->cases()->whereNotIn('status', ['closed', 'resolved'])->count();
        if ($openCases > 0) {
            return response()->json(['message' => "Cannot mark as graduated: student has {$openCases} open case(s)."], 422);
        }

        $student->update([
            'is_active'            => false,
            'deactivation_reason'  => $validated['deactivation_reason'],
            'deactivation_notes'   => $validated['deactivation_notes'] ?? null,
        ]);

        AuditLog::record('graduated', "Deactivated student {$student->student_id}. Reason: {$validated['deactivation_reason']}.", $student);
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

    // Admin-only: view current temp password if the student hasn't changed it yet
    public function viewTempPassword(Student $student)
    {
        if (!$student->must_change_password || !$student->temp_password) {
            return response()->json(['message' => 'This student has already set their own password.'], 404);
        }

        return response()->json(['temp_password' => $student->temp_password]);
    }

    public function resetPassword(Request $request, Student $student)
    {
        $newPassword = Str::random(10);
        $student->update([
            'password'              => Hash::make($newPassword),
            'temp_password'         => $newPassword,
            'must_change_password'  => true,
        ]);
        AuditLog::record('password_reset', "Password reset for student {$student->student_id}.", $student);

        return response()->json([
            'message'       => 'Password reset successfully.',
            'temp_password' => $newPassword,
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
                $errors[] = "Row {$rowNum}: Student ID {$rowData['student_id']} already exists - skipped.";
                $skipped++;
                continue;
            }

            Student::create([
                'student_id'             => $rowData['student_id'],
                'first_name'             => $rowData['first_name'],
                'last_name'              => $rowData['last_name'],
                'middle_name'            => $rowData['middle_name'] ?? null,
                'suffix'                 => $rowData['suffix'] ?? null,
                'sex'                    => $rowData['sex'] ?? null,
                'email'                  => $rowData['email'] ?? null,
                'contact_number'         => $rowData['contact_number'] ?? null,
                'college'                => $rowData['college'] ?? null,
                'program'                => $rowData['program'] ?? null,
                'year_level'             => $rowData['year_level'] ?? null,
                'section'                => $rowData['section'] ?? null,
                'guardian_first_name'    => $rowData['guardian_first_name'] ?? null,
                'guardian_middle_name'   => $rowData['guardian_middle_name'] ?? null,
                'guardian_last_name'     => $rowData['guardian_last_name'] ?? null,
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

        $colleges = [
            'College of Agriculture (CA)',
            'College of Arts and Humanities (CAH)',
            'College of Engineering (CE)',
            'College of Forestry (CF)',
            'College of Human Ecology (CHE)',
            'College of Human Kinetics (CHK)',
            'College of Information Sciences (CIS)',
            'College of Medicine (CM)',
            'College of Natural Sciences (CNS)',
            'College of Numeracy and Applied Sciences (CNAS)',
            'College of Nursing (CN)',
            'College of Public Administration and Governance (CPAG)',
            'College of Social Sciences (CSS)',
            'College of Teacher Education (CTE)',
            'College of Veterinary Medicine (CVM)',
        ];

        // ONE query: fetch every existing student_id that appears in the file
        $existingIds = collect($rows)
            ->pluck('student_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $existingSet = empty($existingIds)
            ? collect()
            : Student::whereIn('student_id', $existingIds)->pluck('student_id')->flip();

        $preview = [];
        foreach ($rows as $i => $rowData) {
            $isDuplicate = !empty($rowData['student_id']) && $existingSet->has($rowData['student_id']);
            $reasons = $this->validateImportRow($rowData, $colleges);

            $preview[] = [
                'row'          => $i + 2,
                'student_id'   => $rowData['student_id'] ?? null,
                'is_duplicate' => $isDuplicate,
                'valid'        => empty($reasons),
                'reasons'      => $reasons,
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

    private function validateImportRow(array $row, array $colleges): array
    {
        $reasons = [];
        $val = fn($k) => trim((string) ($row[$k] ?? ''));

        // Student ID
        $sid = $val('student_id');
        if ($sid === '') {
            $reasons[] = 'Student ID is required';
        } elseif (!ctype_digit($sid)) {
            $reasons[] = 'Student ID must contain only numbers';
        } elseif (strlen($sid) > 15) {
            $reasons[] = 'Student ID is too long (max 15 digits)';
        }

        // Names
        if ($val('first_name') === '') $reasons[] = 'First Name is required';
        if ($val('last_name')  === '') $reasons[] = 'Last Name is required';

        // Sex
        $sex = $val('sex');
        if ($sex === '') {
            $reasons[] = 'Sex is required';
        } elseif (!in_array($sex, ['Male', 'Female'], true)) {
            $reasons[] = 'Sex must be Male or Female';
        }

        // Email
        $email = $val('email');
        if ($email === '') {
            $reasons[] = 'Email Address is required';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $reasons[] = 'Email Address is not valid';
        }

        // Contact Number
        $contact = $val('contact_number');
        if ($contact === '') {
            $reasons[] = 'Contact Number is required';
        } elseif (!preg_match('/^09\d{9}$/', $contact)) {
            $reasons[] = 'Contact Number must start with 09 and be 11 digits';
        }

        // College
        $college = $val('college');
        if ($college === '') {
            $reasons[] = 'College is required';
        } elseif (!in_array($college, $colleges, true)) {
            $reasons[] = 'College name does not match any in the system';
        }

        // Year Level
        $year = $val('year_level');
        if ($year === '') {
            $reasons[] = 'Year Level is required';
        } elseif (!in_array($year, ['1','2','3','4','5','6','7','8','9','10'], true)) {
            $reasons[] = 'Year Level must be a number from 1 to 10';
        }

        // Guardian Contact
        $gcontact = $val('guardian_contact');
        if ($gcontact === '') {
            $reasons[] = 'Guardian Contact is required';
        } elseif (!preg_match('/^09\d{9}$/', $gcontact)) {
            $reasons[] = 'Guardian Contact must start with 09 and be 11 digits';
        } elseif ($gcontact === $contact) {
            $reasons[] = "Guardian Contact can't be the same as the student's contact";
        }

        return $reasons;
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

        // ONE query: fetch every existing student matching any ID in the file
        $existingIds = collect($rows)
            ->pluck('student_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $existing = empty($existingIds)
            ? collect()
            : Student::whereIn('student_id', $existingIds)->get()->keyBy('student_id');

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors  = [];
        $inserts = [];
        $now     = now();

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

            $payload = [
                'first_name'             => $rowData['first_name'],
                'last_name'              => $rowData['last_name'],
                'middle_name'            => $rowData['middle_name'] ?? null,
                'suffix'                 => $rowData['suffix'] ?? null,
                'sex'                    => $rowData['sex'] ?? null,
                'email'                  => $rowData['email'] ?? null,
                'contact_number'         => $rowData['contact_number'] ?? null,
                'college'                => $rowData['college'] ?? null,
                'program'                => $rowData['program'] ?? null,
                'year_level'             => $rowData['year_level'] ?? null,
                'section'                => $rowData['section'] ?? null,
                'guardian_first_name'    => $rowData['guardian_first_name'] ?? null,
                'guardian_middle_name'   => $rowData['guardian_middle_name'] ?? null,
                'guardian_last_name'     => $rowData['guardian_last_name'] ?? null,
                'guardian_contact'       => $rowData['guardian_contact'] ?? null,
                'guardian_relationship'  => $rowData['guardian_relationship'] ?? null,
                'updated_at'             => $now,
            ];

            // Duplicate → update (or skip) using the pre-fetched collection
            if ($existing->has($rowData['student_id'])) {
                if ($decision === 'update') {
                    $existingStudent = $existing->get($rowData['student_id']);
                    $updatePayload = $payload;
                    foreach ($updatePayload as $key => $value) {
                        if ($value === null) {
                            unset($updatePayload[$key]);
                        }
                    }
                    $existingStudent->update($updatePayload);
                    $updated++;
                } else {
                    $skipped++;
                }
                continue;
            }

            // New row — build the insert payload
            // Password uses student_id as the temp; hash at cost 4 for bulk speed
            $inserts[] = array_merge($payload, [
                'student_id'            => $rowData['student_id'],
                'password'              => Hash::make((string) $rowData['student_id'], ['rounds' => 4]),
                'temp_password'         => (string) $rowData['student_id'],
                'must_change_password'  => true,
                'is_active'             => true,
                'created_at'            => $now,
            ]);
            $created++;
        }

        // Bulk insert — one query per 500 rows
        if (!empty($inserts)) {
            DB::transaction(function () use ($inserts) {
                foreach (array_chunk($inserts, 500) as $chunk) {
                    Student::insert($chunk);
                }
            });
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
            'suffix'                  => 'suffix',
            'sex'                     => 'sex',
            'email address'           => 'email',
            'email'                   => 'email',
            'contact number'          => 'contact_number',
            'college'                 => 'college',
            'program'                 => 'program',
            'year level'              => 'year_level',
            'section'                 => 'section',
            'guardian last name'      => 'guardian_last_name',
            'guardian first name'     => 'guardian_first_name',
            'guardian middle name'    => 'guardian_middle_name',
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