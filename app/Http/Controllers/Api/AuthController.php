<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        // Split into distinct checks so the person actually knows what to fix,
        // instead of a single blanket "Invalid credentials." for three very
        // different situations.
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'No BSU Personnel account was found with that email address.'], 401);
        }

        if (!$user->is_active) {
            return response()->json(['message' => 'This account has been deactivated. Please contact the OSS administrator.'], 401);
        }

        if (!Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Incorrect password. Please try again.'], 401);
        }

        $user->update(['last_login_at' => now()]);
        $token = $user->createToken('icare-token')->plainTextToken;

        AuditLog::record('login', "User {$user->name} logged in.");

        return response()->json([
        'token' => $token,
        'user'  => $user->only([
            'id', 'name', 'first_name', 'middle_name', 'last_name',
            'email', 'role', 'unit', 'college', 'department',
            'contact_number', 'employee_id'
        ]),
    ]);
    }

    public function logout(Request $request)
    {
        AuditLog::record('logout', "User {$request->user()->name} logged out.");
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out successfully.']);
    }

    public function me(Request $request)
    {
        return response()->json($request->user());
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'password'         => 'required|min:8|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'Current password is incorrect.'], 422);
        }

        $user->update([
            'password'            => Hash::make($request->password),
            'temp_password'       => null,
            'must_change_password'=> false,
        ]);
        AuditLog::record('password_change', "User {$user->name} changed their password.");

        return response()->json(['message' => 'Password updated successfully.']);
    }
    public function updateProfile(Request $request)
{
    $user = $request->user();

    $validated = $request->validate([
        'first_name'     => 'sometimes|string|max:255',
        'last_name'      => 'sometimes|string|max:255',
        'middle_name'    => 'nullable|string|max:255',
        'email'          => 'sometimes|email|unique:users,email,' . $user->id,
        'contact_number' => 'nullable|string|max:11',
    ]);

    $user->update($validated);

    return response()->json($user->only([
        'id', 'name', 'first_name', 'middle_name', 'last_name',
        'email', 'role', 'unit', 'college', 'department', 'contact_number', 'employee_id'
    ]));
}
}