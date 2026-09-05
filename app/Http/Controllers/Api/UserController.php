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
    public function index(Request $request)
    {
        $query = User::query()
            ->when($request->search, fn($q) => $q->where(fn($sq) =>
                $sq->where('name', 'like', "%{$request->search}%")
                   ->orWhere('email', 'like', "%{$request->search}%")
            ))
            ->when($request->role, fn($q) => $q->where('role', $request->role))
            ->when($request->has('is_active'), fn($q) => $q->where('is_active', $request->is_active));

        return response()->json($query->latest()->paginate(20));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'                  => 'required|string|max:255',
            'email'                 => 'required|email|unique:users,email',
            'employee_id'           => 'nullable|string|max:50',
            'role'                  => 'required|in:admin,gcu_staff,sdu_head,tmdu_staff,faculty,dean_secretary',
            'college'               => 'nullable|string',
            'department'            => 'nullable|string',
            'contact_number'        => 'nullable|string|max:11',
            'password'              => ['required', 'confirmed', 'min:8', 'regex:/[A-Z]/', 'regex:/[0-9]/', 'regex:/[!@#$%^&*(),.?":{}|<>]/'],
        ]);

        $user = User::create([
            ...$validated,
            'password'  => Hash::make($validated['password']),
            'is_active' => true,
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
            'name'           => 'sometimes|string|max:255',
            'email'          => 'sometimes|email|unique:users,email,' . $user->id,
            'employee_id'    => 'nullable|string|max:50',
            'role'           => 'sometimes|in:admin,gcu_staff,sdu_head,tmdu_staff,faculty,dean_secretary',
            'college'        => 'nullable|string',
            'department'     => 'nullable|string',
            'contact_number' => 'nullable|string|max:11',
        ]);

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
        $validated = $request->validate([
            'password' => ['required', 'confirmed', 'min:8', 'regex:/[A-Z]/', 'regex:/[0-9]/', 'regex:/[!@#$%^&*(),.?":{}|<>]/'],
        ]);

        $user->update(['password' => Hash::make($validated['password'])]);
        AuditLog::record('password_reset', "Password reset for {$user->name}.", $user);

        return response()->json(['message' => 'Password reset successfully.']);
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx,xls',
        ]);

        $file = $request->file('file');
        $ext  = strtolower($file->getClientOriginalExtension());

        $headerMap = [
            'name'             => 'name',
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

        $validRoles = ['admin', 'gcu_staff', 'sdu_head', 'tmdu_staff', 'faculty', 'dean_secretary'];
        $created = 0;
        $skipped = 0;
        $errors  = [];
        $rowNum  = 1;

        foreach ($rows as $rowData) {
            $rowNum++;

            if (empty($rowData['name']) || empty($rowData['email']) || empty($rowData['role'])) {
                $errors[] = "Row {$rowNum}: missing required fields (Name, Email, Role).";
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
                $errors[] = "Row {$rowNum}: email {$rowData['email']} already exists — skipped.";
                $skipped++;
                continue;
            }

            User::create([
                'name'           => $rowData['name'],
                'email'          => $rowData['email'],
                'employee_id'    => $rowData['employee_id'] ?? null,
                'role'           => $rowData['role'],
                'college'        => $rowData['college'] ?? null,
                'department'     => $rowData['department'] ?? null,
                'contact_number' => $rowData['contact_number'] ?? null,
                'password'       => Hash::make(Str::random(12)),
                'is_active'      => true,
            ]);
            $created++;
        }

        AuditLog::record('imported', "Bulk imported {$created} employees, skipped {$skipped}.");

        return response()->json([
            'created' => $created,
            'skipped' => $skipped,
            'errors'  => $errors,
        ]);
    }
}