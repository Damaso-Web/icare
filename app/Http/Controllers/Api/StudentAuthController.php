<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Student;
use App\Models\TestingRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class StudentAuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'student_id'        => 'required|string',
            'password'          => 'required|string',
            // Enforced server-side too, not just gated by the frontend UI -
            // a login request without this can't proceed.
            'consent_accepted'  => 'required|accepted',
        ]);

        $student = Student::where('student_id', $request->student_id)
            ->where('is_active', true)
            ->first();

        if (!$student) {
            AuditLog::record('login_failed', "Failed student login: no active account with Student ID {$request->student_id}.");
            return response()->json(['message' => 'No active student account was found with that Student ID.'], 401);
        }

        if (!$student->password || !Hash::check($request->password, $student->password)) {
            AuditLog::record('login_failed', "Failed student login for {$student->student_id}: incorrect password.", $student, [], [], $student);
            return response()->json(['message' => 'Incorrect password.'], 401);
        }

        // The consent notice is shown every time on the login page (not just
        // once), so we simply stamp the latest acceptance on every successful
        // login rather than checking whether it was accepted before.
        $student->update([
            'last_login_at'         => now(),
            'consent_accepted_at'   => now(),
        ]);
        $token = $student->createToken('student-token', ['student'])->plainTextToken;

        AuditLog::record('login', "Student {$student->student_id} logged in.", $student, [], [], $student);

        return response()->json([
            'token'   => $token,
            'student' => $student->only([
                'id', 'student_id', 'first_name', 'middle_name', 'last_name', 'suffix',
                'email', 'contact_number', 'college', 'program', 'year_level', 'must_change_password'
            ]),
        ]);
    }

    public function logout(Request $request)
    {
        $student = $request->user('student');
        AuditLog::record('logout', "Student {$student->student_id} logged out.", $student);
        $student->currentAccessToken()->delete();
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
            'temp_password'         => null,
            'must_change_password'  => false,
        ]);
        AuditLog::record('password_change', "Student {$student->student_id} changed their password.", $student);

        return response()->json(['message' => 'Password updated successfully.']);
    }

    public function dashboard(Request $request)
{
    $student = $request->user('student');

        $pendingAppointments = $student->appointments()
            ->with(['referral', 'case.latestReferral'])
            ->where('request_status', 'awaiting_student')
            ->whereNotIn('status', ['cancelled'])
            ->latest()
            ->get();

    return response()->json([
        'appointments'          => $student->appointments()->with(['staff', 'referral', 'case.latestReferral'])->latest()->get(),
        'referrals'             => $student->referrals()->latest()->get(),
        'pending_appointments'  => $pendingAppointments,
        'pending_appointment'   => $pendingAppointments->first(),
    ]);
}

public function updateProfile(Request $request)
{
    $student = $request->user('student');

    $validated = $request->validate([
        'first_name'     => 'sometimes|string|max:255',
        'last_name'      => 'sometimes|string|max:255',
        'middle_name'    => 'nullable|string|max:255',
        'suffix'         => 'nullable|string|max:20',
        'email'          => 'nullable|email',
        'contact_number' => 'nullable|string|max:11',

        // Family Information - names split into first/middle/last, same as
        // the student's own name and the guardian_first_name/middle/last
        // columns, rather than one plain "name" string.
        'father_first_name'       => 'nullable|string|max:255',
        'father_middle_name'      => 'nullable|string|max:255',
        'father_last_name'        => 'nullable|string|max:255',
        'father_occupation'       => 'nullable|string|max:255',
        'father_contact_number'   => 'nullable|string|max:11',
        'mother_first_name'       => 'nullable|string|max:255',
        'mother_middle_name'      => 'nullable|string|max:255',
        'mother_last_name'        => 'nullable|string|max:255',
        'mother_occupation'       => 'nullable|string|max:255',
        'mother_contact_number'   => 'nullable|string|max:11',

        // Siblings Information - a repeatable list filled in by the student,
        // same first/middle/last split per sibling.
        'siblings'                    => 'nullable|array',
        'siblings.*.first_name'       => 'required|string|max:255',
        'siblings.*.middle_name'      => 'nullable|string|max:255',
        'siblings.*.last_name'        => 'required|string|max:255',
        'siblings.*.age'              => 'nullable|string|max:3',
        'siblings.*.occupation'       => 'nullable|string|max:255',

        // Educational Attainment (school history)
        'elementary_school'               => 'nullable|string|max:255',
        'elementary_year_graduated'       => 'nullable|string|max:4',
        'high_school'                     => 'nullable|string|max:255',
        'high_school_year_graduated'      => 'nullable|string|max:4',
        'college_school'                  => 'nullable|string|max:255',
        'college_year_graduated'          => 'nullable|string|max:4',
    ]);

    $student->fill($validated);
    $changed = array_keys($student->getDirty());

    if ($changed) {
        $old = array_intersect_key($student->getOriginal(), array_flip($changed));
        $student->save();
        AuditLog::record(
            'profile_updated',
            "Student {$student->student_id} updated their own profile (" . implode(', ', $changed) . ").",
            $student,
            $old,
            $student->only($changed)
        );
    }

    return response()->json($student->only([
        'id', 'student_id', 'first_name', 'middle_name', 'last_name', 'suffix',
        'email', 'contact_number', 'college', 'program', 'year_level', 'must_change_password'
    ]));
}

public function showReferral(Request $request, $id)
{
    $student = $request->user('student');
    $referral = $student->referrals()->with('case')->findOrFail($id);

    $pendingAppointment = $student->appointments()
        ->where('request_status', 'awaiting_student')
        ->whereNotIn('status', ['cancelled'])
        ->where(function ($q) use ($referral) {
            $q->where('referral_id', $referral->id);
            if ($referral->case_id) {
                $q->orWhere('case_id', $referral->case_id);
            }
        })
        ->latest()
        ->first();

    $data = $referral->toArray();
    $data['pending_appointment'] = $pendingAppointment;

    return response()->json($data);
}

public function showAppointment(Request $request, $id)
{
    $student = $request->user('student');
    $appointment = $student->appointments()->with(['staff', 'referral', 'case.latestReferral'])->findOrFail($id);
    return response()->json($appointment);
}

// Powers the new "My Testing" student page - lists every TMDU testing
// record tied to this student (GCU->TMDU referral, fee form pickup, OR
// submission, testing schedule, PAR), newest first.
public function testingRecords(Request $request)
{
    $student = $request->user('student');

    $records = TestingRecord::where('student_id', $student->id)
        ->with(['case', 'tester', 'referral'])
        ->latest()
        ->get();

    return response()->json($records);
}

public function showTestingRecord(Request $request, $id)
{
    $student = $request->user('student');

    $record = TestingRecord::where('student_id', $student->id)
        ->with(['case.latestReferral', 'tester', 'documents', 'referral'])
        ->findOrFail($id);

    return response()->json($record);
}

}