<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class StudentAuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'student_id' => 'required|string',
            'password'   => 'required|string',
        ]);

        $student = Student::where('student_id', $request->student_id)
            ->where('is_active', true)
            ->first();

        if (!$student || !$student->password || !Hash::check($request->password, $student->password)) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        $student->update(['last_login_at' => now()]);
        $token = $student->createToken('student-token', ['student'])->plainTextToken;

        return response()->json([
            'token'   => $token,
            'student' => $student->only([
                'id', 'student_id', 'first_name', 'middle_name', 'last_name',
                'email', 'college', 'program', 'year_level', 'must_change_password'
            ]),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user('student')->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out successfully.']);
    }

    public function me(Request $request)
    {
        return response()->json($request->user('student'));
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'password'         => ['required', 'confirmed', 'min:8'],
        ]);

        $student = $request->user('student');

        if (!Hash::check($request->current_password, $student->password)) {
            return response()->json(['message' => 'Current password is incorrect.'], 422);
        }

        $student->update([
            'password'              => Hash::make($request->password),
            'must_change_password'  => false,
        ]);

        return response()->json(['message' => 'Password updated successfully.']);
    }

    public function dashboard(Request $request)
{
    $student = $request->user('student');

    $pendingAppointment = $student->appointments()
        ->where('request_status', 'awaiting_student')
        ->latest()
        ->first();

    return response()->json([
        'appointments'         => $student->appointments()->with('staff')->latest()->get(),
        'referrals'            => $student->referrals()->latest()->get(),
        'pending_appointment'  => $pendingAppointment,
    ]);
}

public function updateProfile(Request $request)
{
    $student = $request->user('student');

    $validated = $request->validate([
        'first_name'     => 'sometimes|string|max:255',
        'last_name'      => 'sometimes|string|max:255',
        'middle_name'    => 'nullable|string|max:255',
        'email'          => 'nullable|email',
        'contact_number' => 'nullable|string|max:11',
    ]);

    $student->update($validated);

    return response()->json($student->only([
        'id', 'student_id', 'first_name', 'middle_name', 'last_name',
        'email', 'contact_number', 'college', 'program', 'year_level', 'must_change_password'
    ]));
}

}