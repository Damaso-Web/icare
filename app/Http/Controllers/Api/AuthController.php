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
    // My Account: a staff member updating their own profile. Role, unit,
    // college, department and employee ID stay Admin-only (User Management).
    public function updateProfile(Request $request)
    {
        $user = $request->user();
        $name = ['string', 'min:2', 'max:255', "regex:/^[a-zA-Z\\s'.-]+$/"];

        $validated = $request->validate([
            'first_name'       => ['required', ...$name],
            'last_name'        => ['required', ...$name],
            'middle_name'      => ['nullable', ...$name],
            'suffix'           => 'nullable|string|max:20',
            'email'            => 'required|email|max:255|unique:users,email,' . $user->id,
            'contact_number'   => ['nullable', 'regex:/^09\d{9}$/'],
            'current_password' => 'nullable|string',
        ], [
            'first_name.regex'     => 'Names may only contain letters, spaces, apostrophes, periods and hyphens.',
            'last_name.regex'      => 'Names may only contain letters, spaces, apostrophes, periods and hyphens.',
            'middle_name.regex'    => 'Names may only contain letters, spaces, apostrophes, periods and hyphens.',
            'contact_number.regex' => 'Contact number must be 11 digits starting with 09 (e.g. 09171234567).',
        ]);

        // The email is the login username, so changing it needs the current password.
        $emailChanged = strcasecmp($validated['email'], $user->email) !== 0;
        if ($emailChanged) {
            if (strtolower($user->email) === DevController::TESTER_EMAIL) {
                return response()->json(['message' => "The tester account's email can't be changed - the role switcher is tied to it."], 422);
            }
            if (empty($validated['current_password']) || !Hash::check($validated['current_password'], $user->password)) {
                return response()->json(['message' => 'Current password is incorrect. Your email was not changed.'], 422);
            }
        }
        unset($validated['current_password']);

        $old = $user->only(array_keys($validated));
        $user->fill($validated);
        $changed = array_keys($user->getDirty());
        $changed = array_values(array_diff($changed, ['name']));

        if ($changed) {
            $user->save();
            AuditLog::record(
                'profile_updated',
                "User {$user->name} updated their own profile (" . implode(', ', $changed) . ").",
                $user,
                array_intersect_key($old, array_flip($changed)),
                $user->only($changed)
            );
        }

        return response()->json($user->only([
            'id', 'name', 'first_name', 'middle_name', 'last_name', 'suffix',
            'email', 'role', 'unit', 'college', 'department', 'contact_number', 'employee_id'
        ]));
    }
}