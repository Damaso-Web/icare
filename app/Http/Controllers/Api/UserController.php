<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query()
            ->when($request->role,   fn($q) => $q->where('role', $request->role))
            ->when($request->unit,   fn($q) => $q->where('unit', $request->unit))
            ->when($request->has('is_active'), fn($q) => $q->where('is_active', $request->is_active))
            ->when($request->search, fn($q) =>
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%")
                  ->orWhere('employee_id', 'like', "%{$request->search}%")
            );

        return response()->json($query->latest()->paginate(20));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'           => 'required|string',
            'email'          => 'required|email|unique:users,email',
            'employee_id'    => 'nullable|string|unique:users,employee_id',
            'password' => ['required', 'confirmed', 'min:8', 'regex:/[A-Z]/', 'regex:/[0-9]/', 'regex:/[!@#$%^&*(),.?":{}|<>]/'],
            'role'           => 'required|in:admin,gcu_staff,sdu_head,tmdu_staff,faculty,dean_secretary',
            'college'        => 'nullable|string',
            'department'     => 'nullable|string',
            'contact_number' => 'nullable|string|max:11'
        ]);

        $validated['password'] = Hash::make($validated['password']);
        $user = User::create($validated);

        AuditLog::record('created', "Created user account for {$user->name} ({$user->role}).", $user);
        return response()->json($user, 201);
    }

    public function show(User $user)
    {
        return response()->json($user->load(['availability']));
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name'           => 'sometimes|string',
            'email'          => 'sometimes|email|unique:users,email,' . $user->id,
            'employee_id'    => 'nullable|string|unique:users,employee_id,' . $user->id,
            'role'           => 'sometimes|in:admin,gcu_staff,sdu_head,tmdu_staff,faculty,dean_secretary',
            'college'        => 'nullable|string',
            'department'     => 'nullable|string',
            'contact_number' => 'nullable|string',
        ]);

        $old = $user->toArray();
        $user->update($validated);
        AuditLog::record('updated', "Updated user account for {$user->name}.", $user, $old, $user->toArray());
        return response()->json($user);
    }

    public function destroy(User $user)
    {
        AuditLog::record('deleted', "Deleted user account for {$user->name}.", $user);
        $user->delete();
        return response()->json(['message' => 'User deleted.']);
    }

    public function toggleActive(Request $request, User $user)
    {
        $user->update(['is_active' => !$user->is_active]);
        $status = $user->is_active ? 'activated' : 'deactivated';
        AuditLog::record('toggled', "User {$user->name} {$status}.", $user);
        return response()->json(['message' => "User {$status}.", 'is_active' => $user->is_active]);
    }

    public function resetPassword(Request $request, User $user)
    {
        $request->validate(['password' => 'required|min:8|confirmed']);
        $user->update(['password' => Hash::make($request->password)]);
        AuditLog::record('password_reset', "Password reset for user {$user->name}.", $user);
        return response()->json(['message' => 'Password reset successfully.']);
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

    $required = ['name', 'email', 'role'];
    foreach ($required as $col) {
        if (!in_array($col, $header)) {
            fclose($handle);
            return response()->json(['message' => "Missing required column: {$col}"], 422);
        }
    }

    $validRoles = ['admin', 'gcu_staff', 'sdu_head', 'tmdu_staff', 'faculty', 'dean_secretary'];
    $created = 0;
    $skipped = 0;
    $errors  = [];
    $row = 1;

    while (($data = fgetcsv($handle)) !== false) {
        $row++;
        $rowData = array_combine($header, $data);

        if (empty($rowData['name']) || empty($rowData['email']) || empty($rowData['role'])) {
            $errors[] = "Row {$row}: missing required fields.";
            $skipped++;
            continue;
        }

        if (!in_array($rowData['role'], $validRoles)) {
            $errors[] = "Row {$row}: invalid role '{$rowData['role']}'.";
            $skipped++;
            continue;
        }

        $exists = User::where('email', $rowData['email'])->exists();
        if ($exists) {
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
            'password'       => Hash::make($rowData['password'] ?? 'ChangeMe@123'),
            'is_active'      => true,
        ]);
        $created++;
    }

    fclose($handle);

    AuditLog::record('imported', "Bulk imported {$created} employees, skipped {$skipped}.");

    return response()->json([
        'created' => $created,
        'skipped' => $skipped,
        'errors'  => $errors,
    ]);
}
}