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
            'contact_number' => 'nullable|string',
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
}