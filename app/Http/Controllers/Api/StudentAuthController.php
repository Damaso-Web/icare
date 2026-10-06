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
    // Same shared patterns as StudentController - kept in sync there.
    // Only ever applied to plain <input> fields, never to free-text
    // <textarea>-backed fields (which need normal punctuation).
    private const NAME_REGEX  = '/^[a-zA-Z\x{00C0}-\x{024F}\'\-\.\s]+$/u';
    private const TEXT_REGEX  = '/^[a-zA-Z0-9\x{00C0}-\x{024F}\'\-\.\,\&\(\)\s]+$/u';
    private const PHONE_REGEX = '/^[0-9\+\-\s]+$/';
    private const YEAR_REGEX  = '/^[0-9]{4}$/';
    private const AGE_REGEX   = '/^[0-9]{1,3}$/';

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
            return response()->json(['message' => 'Invalid Student ID or password.'], 401);
        }

        if (!$student->password || !Hash::check($request->password, $student->password)) {
            AuditLog::record('login_failed', "Failed student login for {$student->student_id}: incorrect password.", $student, [], [], $student);
            return response()->json(['message' => 'Invalid Student ID or password.'], 401);
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
            'password'         => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::min(8)->letters()->mixedCase()->numbers()->symbols()],
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
        'parent_conference_slips' => $this->studentParentConferenceSlips($student),
    ]);
}

public function parentConferenceSlips(Request $request)
{
    return response()->json($this->studentParentConferenceSlips($request->user('student')));
}

private function studentParentConferenceSlips($student)
{
    return \App\Models\ParentConferenceSlip::whereIn('case_id', $student->cases()->pluck('id'))
        ->latest('issued_at')
        ->get(['id', 'case_id', 'conference_date', 'conference_time', 'reason', 'remarks', 'issued_at']);
}

public function updateProfile(Request $request)
{
    $student = $request->user('student');

    $validated = $request->validate([
        'first_name'     => ['sometimes', 'string', 'max:255', 'regex:' . self::NAME_REGEX],
        'last_name'      => ['sometimes', 'string', 'max:255', 'regex:' . self::NAME_REGEX],
        'middle_name'    => ['nullable', 'string', 'max:255', 'regex:' . self::NAME_REGEX],
        'suffix'         => ['nullable', 'string', 'max:20', 'regex:' . self::NAME_REGEX],
        'email'          => 'nullable|email',
        'contact_number' => ['nullable', 'string', 'max:11', 'regex:' . self::PHONE_REGEX],

        // Personal Information (Student Information Sheet)
        'birthdate'      => 'nullable|date|before:today',
        'sex'            => 'nullable|in:Male,Female',
        'civil_status'   => 'nullable|in:Single,Married,Divorced,Widowed,Separated',
        'nationality'    => ['nullable', 'string', 'max:100', 'regex:' . self::NAME_REGEX],
        'birthplace'     => ['nullable', 'string', 'max:255', 'regex:' . self::TEXT_REGEX],
        'languages'      => ['nullable', 'string', 'max:255', 'regex:' . self::TEXT_REGEX],
        'address'        => ['nullable', 'string', 'max:255', 'regex:' . self::TEXT_REGEX],

        // Family Information - names split into first/middle/last, same as
        // the student's own name and the guardian_first_name/middle/last
        // columns, rather than one plain "name" string.
        'father_first_name'       => ['nullable', 'string', 'max:255', 'regex:' . self::NAME_REGEX],
        'father_middle_name'      => ['nullable', 'string', 'max:255', 'regex:' . self::NAME_REGEX],
        'father_last_name'        => ['nullable', 'string', 'max:255', 'regex:' . self::NAME_REGEX],
        'father_occupation'       => ['nullable', 'string', 'max:255', 'regex:' . self::TEXT_REGEX],
        'father_contact_number'   => ['nullable', 'string', 'max:11', 'regex:' . self::PHONE_REGEX],
        'mother_first_name'       => ['nullable', 'string', 'max:255', 'regex:' . self::NAME_REGEX],
        'mother_middle_name'      => ['nullable', 'string', 'max:255', 'regex:' . self::NAME_REGEX],
        'mother_last_name'        => ['nullable', 'string', 'max:255', 'regex:' . self::NAME_REGEX],
        'mother_occupation'       => ['nullable', 'string', 'max:255', 'regex:' . self::TEXT_REGEX],
        'mother_contact_number'   => ['nullable', 'string', 'max:11', 'regex:' . self::PHONE_REGEX],
        'father_age'                      => ['nullable', 'string', 'max:3', 'regex:' . self::AGE_REGEX],
        'father_educational_attainment'   => ['nullable', 'string', 'max:255', 'regex:' . self::TEXT_REGEX],
        'mother_age'                      => ['nullable', 'string', 'max:3', 'regex:' . self::AGE_REGEX],
        'mother_educational_attainment'   => ['nullable', 'string', 'max:255', 'regex:' . self::TEXT_REGEX],
        'guardian_first_name'             => ['nullable', 'string', 'max:255', 'regex:' . self::NAME_REGEX],
        'guardian_middle_name'            => ['nullable', 'string', 'max:255', 'regex:' . self::NAME_REGEX],
        'guardian_last_name'              => ['nullable', 'string', 'max:255', 'regex:' . self::NAME_REGEX],
        'guardian_age'                    => ['nullable', 'string', 'max:3', 'regex:' . self::AGE_REGEX],
        'guardian_occupation'             => ['nullable', 'string', 'max:255', 'regex:' . self::TEXT_REGEX],
        'guardian_educational_attainment' => ['nullable', 'string', 'max:255', 'regex:' . self::TEXT_REGEX],
        'guardian_contact'                => ['nullable', 'string', 'max:11', 'regex:' . self::PHONE_REGEX],

        // Siblings Information - a repeatable list filled in by the student,
        // same first/middle/last split per sibling.
        'siblings'                    => 'nullable|array',
        'siblings.*.first_name'       => ['required', 'string', 'max:255', 'regex:' . self::NAME_REGEX],
        'siblings.*.middle_name'      => ['nullable', 'string', 'max:255', 'regex:' . self::NAME_REGEX],
        'siblings.*.last_name'        => ['required', 'string', 'max:255', 'regex:' . self::NAME_REGEX],
        'siblings.*.age'              => ['nullable', 'string', 'max:3', 'regex:' . self::AGE_REGEX],
        'siblings.*.occupation'       => ['nullable', 'string', 'max:255', 'regex:' . self::TEXT_REGEX],
        'siblings.*.educational_attainment' => ['nullable', 'string', 'max:255', 'regex:' . self::TEXT_REGEX],
        'siblings.*.civil_status'     => 'nullable|in:Single,Married,Divorced,Widowed,Separated',

        // Educational Attainment (school history)
        'elementary_school'               => ['nullable', 'string', 'max:255', 'regex:' . self::TEXT_REGEX],
        'elementary_year_graduated'       => ['nullable', 'string', 'regex:' . self::YEAR_REGEX],
        'high_school'                     => ['nullable', 'string', 'max:255', 'regex:' . self::TEXT_REGEX],
        'high_school_year_graduated'      => ['nullable', 'string', 'regex:' . self::YEAR_REGEX],
        'college_school'                  => ['nullable', 'string', 'max:255', 'regex:' . self::TEXT_REGEX],
        'college_year_graduated'          => ['nullable', 'string', 'regex:' . self::YEAR_REGEX],
        'senior_high_school'              => ['nullable', 'string', 'max:255', 'regex:' . self::TEXT_REGEX],
        'senior_high_year_graduated'      => ['nullable', 'string', 'regex:' . self::YEAR_REGEX],
        'senior_high_achievements'        => 'nullable|string|max:1000',
        'high_school_achievements'        => 'nullable|string|max:1000',
        'elementary_achievements'         => 'nullable|string|max:1000',
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