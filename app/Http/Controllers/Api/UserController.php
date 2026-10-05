<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserController extends Controller
{
    // Plain <input> fields only. Email and password are intentionally exempt.
    private const NAME_REGEX  = '/^[a-zA-Z\x{00C0}-\x{024F}\'\-\.\s]+$/u';
    private const PHONE_REGEX = '/^[0-9\+\-\s]+$/';
    private const ID_REGEX    = '/^[A-Za-z0-9\-]+$/';

    /**
     * Roles the currently authenticated actor may assign.
     * system_admin can assign anything; admin (GCU Head) cannot grant system_admin.
     */
    private function assignableRoles(): array
    {
        $all = ['admin', 'gcu_staff', 'sdu_head', 'tmdu_staff', 'faculty', 'dean_secretary', 'system_admin'];

        return request()->user()?->isSystemAdmin()
            ? $all
            : array_values(array_diff($all, ['system_admin']));
    }

    // Lightweight, read-only staff list for the Reassign dropdowns. Unlike
    // index() this is open to every OSS staff role (the Users route group is
    // admin-only, which is why those dropdowns used to 403 and glitch).
    public function roster(Request $request)
    {
        abort_unless(in_array($request->user()->role, ['admin', 'gcu_staff', 'sdu_head', 'tmdu_staff'], true), 403, 'Unauthorized.');

        return response()->json(
            User::where('is_active', true)
                ->when($request->role, fn($q) => $q->whereIn('role', (array) $request->role))
                ->when($request->unit, fn($q) => $q->where('unit', $request->unit))
                ->whereIn('role', ['admin', 'gcu_staff', 'sdu_head', 'tmdu_staff'])
                ->orderBy('name')
                ->get(['id', 'name', 'role', 'unit'])
        );
    }

    public function index(Request $request)
    {
        $query = User::query()
            ->when($request->search, fn($q) => $q->where(fn($sq) =>
                $sq->where('name', 'like', "%{$request->search}%")
                   ->orWhere('email', 'like', "%{$request->search}%")
            ))
            ->when($request->role, fn($q) => $q->where('role', $request->role))
            ->when($request->unit, fn($q) => $q->where('unit', $request->unit))
            ->when($request->college, fn($q) => $q->where('college', $request->college))
            ->when($request->has('is_active'), fn($q) => $q->where('is_active', $request->is_active));

        $sortBy  = in_array($request->sort_by, ['created_at', 'employee_id', 'last_name']) ? $request->sort_by : 'created_at';
        $sortDir = $request->sort_dir === 'asc' ? 'asc' : 'desc';
        if ($sortBy === 'last_name') {
            $query->orderBy('last_name', $sortDir)->orderBy('first_name', $sortDir);
        } else {
            $query->orderBy($sortBy, $sortDir);
        }

        return response()->json($query->paginate(20));
    }

        public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name'            => ['required', 'string', 'max:255', 'regex:' . self::NAME_REGEX],
            'middle_name'           => ['nullable', 'string', 'max:255', 'regex:' . self::NAME_REGEX],
            'last_name'             => ['required', 'string', 'max:255', 'regex:' . self::NAME_REGEX],
            'suffix'                => ['nullable', 'string', 'max:20', 'regex:' . self::NAME_REGEX],
            'email'                 => 'required|email|unique:users,email',
            'employee_id'           => ['nullable', 'string', 'max:50', 'regex:' . self::ID_REGEX],
            'role'                  => ['required', \Illuminate\Validation\Rule::in($this->assignableRoles())],
            'college'               => 'nullable|string|max:255',
            'department'            => 'nullable|string|max:255',
            'contact_number'        => ['nullable', 'string', 'max:11', 'regex:' . self::PHONE_REGEX],
            'password'              => ['required', 'confirmed', 'min:8', 'regex:/[A-Z]/', 'regex:/[0-9]/', 'regex:/[!@#$%^&*(),.?":{}|<>]/'],
        ]);

        $user = User::create([
            ...$validated,
            'name'                  => trim($validated['first_name'] . ' ' . $validated['last_name']),
            'password'              => Hash::make($validated['password']),
            'temp_password'         => $validated['password'],
            'must_change_password'  => true,
            'is_active'             => true,
        ]);

        AuditLog::record('created', "Created employee account for {$user->name} ({$user->role}).", $user);

        return response()->json($user, 201);
    }

    public function show(User $user)
    {
        return response()->json($user);
    }

        public function update(Request $request, User $user)
{
    $validated = $request->validate([
        'first_name'     => ['sometimes', 'string', 'max:255', 'regex:' . self::NAME_REGEX],
        'middle_name'    => ['nullable', 'string', 'max:255', 'regex:' . self::NAME_REGEX],
        'last_name'      => ['sometimes', 'string', 'max:255', 'regex:' . self::NAME_REGEX],
        'suffix'         => ['nullable', 'string', 'max:20', 'regex:' . self::NAME_REGEX],
        'email'          => 'sometimes|email|unique:users,email,' . $user->id,
        'employee_id'    => ['nullable', 'string', 'max:50', 'regex:' . self::ID_REGEX],
        'role'           => ['sometimes', \Illuminate\Validation\Rule::in($this->assignableRoles())],
        'college'        => 'nullable|string|max:255',
        'department'     => 'nullable|string|max:255',
        'contact_number' => ['nullable', 'string', 'max:11', 'regex:' . self::PHONE_REGEX],
    ]);

    // Prevent a user from changing their own role (self-escalation guard).
    if (isset($validated['role']) && $request->user()->id === $user->id) {
        unset($validated['role']);
    }

    $old = $user->toArray();
    $user->update($validated);

    AuditLog::record('updated', "Updated employee account for {$user->name}.", $user, $old, $user->toArray());

    return response()->json($user);
}

    public function destroy(User $user)
    {
        $user->delete();
        AuditLog::record('deleted', "Deleted employee account for {$user->name}.");
        return response()->json(['message' => 'User deleted.']);
    }

    public function toggleActive(User $user)
    {
        $user->update(['is_active' => !$user->is_active]);
        AuditLog::record('toggled', "User {$user->name} " . ($user->is_active ? 'activated' : 'deactivated') . ".", $user);
        return response()->json($user);
    }

    public function resetPassword(Request $request, User $user)
{
    $newPassword = Str::random(10);
    $user->update([
        'password'              => Hash::make($newPassword),
        'temp_password'         => $newPassword,
        'must_change_password'  => true,
    ]);
    AuditLog::record('password_reset', "Password reset for {$user->name}.", $user);

    return response()->json([
        'message'       => 'Password reset successfully.',
        'temp_password' => $newPassword,
    ]);
}

    // Admin-only: view current temp password if the user hasn't changed it yet
    public function viewTempPassword(User $user)
    {
        if (!$user->must_change_password || !$user->temp_password) {
            return response()->json(['message' => 'This user has already set their own password.'], 404);
        }

        return response()->json(['temp_password' => $user->temp_password]);
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx,xls',
        ]);

        $file = $request->file('file');
        $ext  = strtolower($file->getClientOriginalExtension());

        $headerMap = [
            'last name'        => 'last_name',
            'first name'       => 'first_name',
            'middle name'      => 'middle_name',
            'suffix'           => 'suffix',
            'email address'    => 'email',
            'email'            => 'email',
            'role'             => 'role',
            'employee id'      => 'employee_id',
            'college'          => 'college',
            'department'       => 'department',
            'contact number'   => 'contact_number',
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

        $validRoles = $this->assignableRoles();
        $created = 0;
        $skipped = 0;
        $errors  = [];
        $rowNum  = 1;
        $generatedPasswords = [];

        foreach ($rows as $rowData) {
            $rowNum++;
            $rowData['role'] = strtolower(trim($rowData['role'] ?? ''));

            if (empty($rowData['first_name']) || empty($rowData['last_name']) || empty($rowData['email']) || empty($rowData['role'])) {
                $errors[] = "Row {$rowNum}: missing required fields (Last Name, First Name, Email, Role).";
                $skipped++;
                continue;
            }

            if (!in_array($rowData['role'], $validRoles)) {
                $errors[] = "Row {$rowNum}: invalid role '{$rowData['role']}'.";
                $skipped++;
                continue;
            }

            $exists = User::where('email', $rowData['email'])->exists();
            if ($exists) {
                $errors[] = "Row {$rowNum}: email {$rowData['email']} already exists - skipped.";
                $skipped++;
                continue;
            }

            $tempPassword = Str::random(10);
            User::create([
                'first_name'            => $rowData['first_name'],
                'middle_name'           => $rowData['middle_name'] ?? null,
                'last_name'             => $rowData['last_name'],
                'suffix'                => $rowData['suffix'] ?? null,
                'name'                  => trim($rowData['first_name'] . ' ' . $rowData['last_name']),
                'email'                 => $rowData['email'],
                'employee_id'           => $rowData['employee_id'] ?? null,
                'role'                  => $rowData['role'],
                'college'               => $rowData['college'] ?? null,
                'department'            => $rowData['department'] ?? null,
                'contact_number'        => $rowData['contact_number'] ?? null,
                'password'              => Hash::make($tempPassword),
                'temp_password'         => $tempPassword,
                'must_change_password'  => true,
                'is_active'             => true,
            ]);
            $created++;
            $generatedPasswords[] = [
                'email'         => $rowData['email'],
                'name'          => trim($rowData['first_name'] . ' ' . $rowData['last_name']),
                'temp_password' => $tempPassword,
            ];
        }

        AuditLog::record('imported', "Bulk imported {$created} employees, skipped {$skipped}.");

        return response()->json([
            'created'   => $created,
            'skipped'   => $skipped,
            'errors'    => $errors,
            'passwords' => $generatedPasswords,
        ]);
    }

    /**
     * Column-header map for the Faculty/Employee masterlist templates,
     * mirroring StudentController's studentHeaderMap() pattern.
     */
    private function employeeHeaderMap(): array
    {
        return [
            'last name'      => 'last_name',
            'first name'     => 'first_name',
            'middle name'    => 'middle_name',
            'suffix'         => 'suffix',
            'email address'  => 'email',
            'email'          => 'email',
            'role'           => 'role',
            'employee id'    => 'employee_id',
            'college'        => 'college',
            'department'     => 'department',
            'contact number' => 'contact_number',
        ];
    }

    /**
     * Parses an uploaded masterlist file (xlsx/xls/csv/txt) into an array of
     * associative rows, keyed by the mapped field names in $headerMap.
     * Same parsing logic already used inline by import(), just reusable.
     */
    private function parseFile($file, string $ext, array $headerMap): array
    {
        $rows = [];

        if (in_array($ext, ['xlsx', 'xls'])) {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getRealPath());
            $sheet = $spreadsheet->getActiveSheet();
            $data  = $sheet->toArray(null, true, true, false);

            if (empty($data)) {
                return [];
            }

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

    /**
     * Validates a single parsed masterlist row for the Faculty/Employee import,
     * mirroring StudentController's validateImportRow() pattern.
     */
    private function validateImportRow(array $row, array $validRoles): array
    {
        $reasons = [];

        if (empty($row['last_name'])) {
            $reasons[] = 'Last Name is required.';
        }

        if (empty($row['first_name'])) {
            $reasons[] = 'First Name is required.';
        }

        if (empty($row['email'])) {
            $reasons[] = 'Email is required.';
        } elseif (!filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
            $reasons[] = 'Email address is invalid.';
        }

        if (empty($row['role'])) {
            $reasons[] = 'Role is required.';
        } elseif (!in_array($row['role'], $validRoles)) {
            $reasons[] = "Invalid role '{$row['role']}'.";
        }

        return $reasons;
    }

    /**
     * Step 1 of the Faculty/Employee masterlist upload: parse + validate
     * every row up front and return a preview (new/duplicate/invalid) without
     * writing anything to the database yet. Mirrors
     * StudentController::importPreview().
     */
    public function importPreview(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx,xls',
        ]);

        $file = $request->file('file');
        $ext  = strtolower($file->getClientOriginalExtension());
        $rows = $this->parseFile($file, $ext, $this->employeeHeaderMap());

        if (empty($rows)) {
            return response()->json(['preview' => [], 'total' => 0, 'duplicates' => 0, 'token' => '']);
        }

        $validRoles = $this->assignableRoles();

        $emails = collect($rows)
            ->pluck('email')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $existingEmails = User::whereIn('email', $emails)->pluck('email')->flip();

        $preview = [];
        $duplicateCount = 0;

        $existingUsers = User::whereIn('email', $emails)->get()->keyBy('email');

        foreach ($rows as $i => $row) {
            $row['role']  = strtolower(trim($row['role'] ?? ''));
            $reasons      = $this->validateImportRow($row, $validRoles);
            $isDuplicate  = !empty($row['email']) && isset($existingEmails[$row['email']]);

            if ($isDuplicate) {
                $duplicateCount++;

                // Protect existing admin/system_admin accounts: a bulk
                // masterlist import must never be able to change the role
                // of a privileged account (e.g. an admin's email accidentally
                // reused as a "faculty" row would otherwise silently demote
                // them). Flag it invalid so it can't be actioned as update.
                $existingUser = $existingUsers->get($row['email']);
                if ($existingUser && in_array($existingUser->role, ['admin', 'system_admin'], true)) {
                    $reasons[] = "This email belongs to a protected {$existingUser->role} account and cannot be modified by import.";
                }
            }

            $preview[] = [
                'row'          => $i + 2,
                'email'        => $row['email'] ?? '',
                'name'         => trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')),
                'role'         => $row['role'] ?? '',
                'is_duplicate' => $isDuplicate,
                'valid'        => empty($reasons),
                'reasons'      => $reasons,
            ];
        }

        $token = uniqid('import_');
        cache()->put($token, $rows, now()->addMinutes(15));

        return response()->json([
            'token'      => $token,
            'preview'    => $preview,
            'total'      => count($rows),
            'duplicates' => $duplicateCount,
        ]);
    }

    /**
     * Step 2 of the Faculty/Employee masterlist upload: re-fetch the cached,
     * already-validated rows by token and actually create/update/skip each
     * one according to the admin's per-row decisions. Mirrors
     * StudentController::importConfirm().
     */
    public function importConfirm(Request $request)
    {
        $request->validate([
            'token'     => 'required|string',
            'decisions' => 'required|array',
        ]);

        $rows = cache()->get($request->token);

        if (!$rows) {
            return response()->json(['message' => 'Import session expired. Please upload the file again.'], 422);
        }

        $decisions  = $request->decisions;
        $validRoles = $this->assignableRoles();

        $emails   = collect($rows)->pluck('email')->filter()->unique()->values()->all();
        $existing = User::whereIn('email', $emails)->get()->keyBy('email');

        $created   = 0;
        $updated   = 0;
        $skipped   = 0;
        $errors    = [];
        $passwords = [];

        foreach ($rows as $i => $row) {
            $decision = $decisions[$i] ?? 'skip';

            if ($decision === 'skip') {
                $skipped++;
                continue;
            }

            $row['role'] = strtolower(trim($row['role'] ?? ''));
            $reasons = $this->validateImportRow($row, $validRoles);
            if (!empty($reasons)) {
                $skipped++;
                $errors[] = 'Row ' . ($i + 2) . ': ' . implode(' ', $reasons);
                continue;
            }

            $existingUser = $existing->get($row['email']);

            // Never let a bulk import modify a protected admin/system_admin
            // account, even if the frontend sent an 'update' decision for it
            // (e.g. importPreview already flagged this row invalid).
            if ($existingUser && in_array($existingUser->role, ['admin', 'system_admin'], true)) {
                $skipped++;
                $errors[] = 'Row ' . ($i + 2) . ": email {$row['email']} belongs to a protected {$existingUser->role} account and was not modified.";
                continue;
            }

            $payload = [
                'first_name'     => $row['first_name'],
                'middle_name'    => $row['middle_name'] ?? null,
                'last_name'      => $row['last_name'],
                'suffix'         => $row['suffix'] ?? null,
                'name'           => trim($row['first_name'] . ' ' . $row['last_name']),
                'email'          => $row['email'],
                'role'           => $row['role'],
                'employee_id'    => $row['employee_id'] ?? null,
                'college'        => $row['college'] ?? null,
                'department'     => $row['department'] ?? null,
                'contact_number' => $row['contact_number'] ?? null,
            ];

                        try {
                if ($existingUser) {
                    // decision is 'update' (or 'create' re-hitting an existing
                    // email, which is also treated as an update to avoid a
                    // duplicate-email database error).
                    $existingUser->update(array_filter($payload, fn($v) => $v !== null && $v !== ''));
                    $updated++;
                } else {
                    $tempPassword = Str::random(10);
                    $payload['password']             = Hash::make($tempPassword);
                    $payload['temp_password']        = $tempPassword;
                    $payload['must_change_password'] = true;
                    $payload['is_active']            = true;

                    $user = User::create($payload);

                    $passwords[] = [
                        'email'         => $user->email,
                        'name'          => $user->name,
                        'temp_password' => $tempPassword,
                    ];
                    $created++;
                }
            } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                // A duplicate value on some other unique column (e.g.
                // employee_id reused across rows or already taken by an
                // existing account) must not crash the whole batch - skip
                // just this row and report it.
                $skipped++;
                $errors[] = 'Row ' . ($i + 2) . ": could not save {$row['email']} - a unique field (such as Employee ID) is already in use.";
            }
        }

        cache()->forget($request->token);

        AuditLog::record('imported', "Bulk imported {$created} employees, updated {$updated}, skipped {$skipped}.");

        return response()->json([
            'created'   => $created,
            'updated'   => $updated,
            'skipped'   => $skipped,
            'errors'    => $errors,
            'passwords' => $passwords,
        ]);
    }
}