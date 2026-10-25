<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Notifications\ChangePasswordReminderNotification;
use App\Notifications\PasswordChangedNotification;
use App\Notifications\RegistrationOtpNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
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
            'password'          => 'required|string|max:255',
            // Enforced server-side too, not just gated by the frontend UI -
            // a login request without this can't proceed.
            'consent_accepted'  => 'required|accepted',
        ]);

        // Too many wrong passwords in a row locks this Student ID for a few minutes.
        $guard = new \App\Support\LoginGuard('student', $request->student_id);
        if ($minutes = $guard->lockedMinutes()) {
            AuditLog::record('login_locked', "Student sign-in refused for {$request->student_id}: locked after repeated incorrect attempts.");
            return response()->json(['message' => \App\Support\LoginGuard::lockedMessage($minutes), 'locked' => true], 429);
        }

        $student = Student::where('student_id', $request->student_id)
            ->where('is_active', true)
            ->first();

        if (!$student) {
            AuditLog::record('login_failed', "Failed student login: no active account with Student ID {$request->student_id}.");
            $left = $guard->fail();
            return response()->json(['message' => 'Invalid Student ID or password.' . \App\Support\LoginGuard::attemptsMessage($left), 'attempts_left' => $left], 401);
        }

        if (!$student->password || !Hash::check($request->password, $student->password)) {
            $left = $guard->fail();
            AuditLog::record('login_failed', "Failed student login for {$student->student_id}: incorrect password" . ($left === 0 ? ' - sign-in locked for ' . \App\Support\LoginGuard::LOCK_MINUTES . ' minutes.' : " ({$left} attempt(s) left)."), $student, [], [], $student);
            return response()->json(['message' => 'Invalid Student ID or password.' . \App\Support\LoginGuard::attemptsMessage($left), 'attempts_left' => $left], 401);
        }

        // A one-time password (from registering) works only for a short time.
        if ($student->temp_password_expires_at && $student->temp_password_expires_at->isPast()) {
            return response()->json([
                'error'   => 'otp_expired',
                'message' => 'Your one-time password has expired. Please register again to get a new one.',
            ], 401);
        }

        $guard->clear();

        // The consent notice is shown every time on the login page (not just
        // once), so we simply stamp the latest acceptance on every successful
        // login rather than checking whether it was accepted before.
        $student->update([
            'last_login_at'         => now(),
            'consent_accepted_at'   => now(),
        ]);
        // B569: label the token with the device/browser it was issued to,
        // so "Active Sessions" (see sessions()) shows something recognizable.
        $deviceLabel = \App\Support\DeviceLabel::fromRequest($request);
        // B630: checked against the account's EXISTING sessions, so this has
        // to run before createToken() below adds the one for this login.
        \App\Support\NewDeviceLoginAlert::maybeNotify($student, $request, $deviceLabel);
        $token = $student->createToken($deviceLabel, ['student'])->plainTextToken;

        // Still on a temporary / one-time password: remind them in the bell
        // (once, until they have read it).
        if ($student->must_change_password && !$student->unreadNotifications()->where('type', ChangePasswordReminderNotification::class)->exists()) {
            $student->notify(new ChangePasswordReminderNotification());
        }

        AuditLog::record('login', "Student {$student->student_id} logged in.", $student, [], [], $student);

        return response()->json([
            'token'   => $token,
            'student' => $student->only([
                'id', 'student_id', 'first_name', 'middle_name', 'last_name', 'suffix',
                'email', 'contact_number', 'college', 'program', 'year_level', 'must_change_password'
            ]),
        ]);
    }

    // First-time sign-up: the Student ID must be in the list the GCU uploaded.
    // The student gives their email, we send a one-time password to it, and that
    // password works as their password until they change it.
    public const OTP_MINUTES = 30;

    public function register(Request $request)
    {
        $data = $request->validate([
            'student_id'       => 'required|string|max:20',
            'email'            => ['required', 'email', 'max:100', new \App\Rules\CompleteEmail],
            'consent_accepted' => 'required|accepted',
        ]);
        $email = strtolower(trim($data['email']));

        $student = Student::where('student_id', trim($data['student_id']))->first();
        if (!$student) {
            throw ValidationException::withMessages(['student_id' => 'This Student ID is not in the student list uploaded by the Guidance and Counseling Unit. Please contact the Office of Student Services.']);
        }
        if (!$student->is_active) {
            throw ValidationException::withMessages(['student_id' => 'This account is not active. Please contact the Office of Student Services.']);
        }
        // Already set their own password: nothing to register.
        if (!$student->must_change_password) {
            throw ValidationException::withMessages(['student_id' => 'This Student ID already has an account. Please sign in, or use "Forgot password?" if you cannot remember your password.']);
        }
        // An email already on file (from the GCU list) must be the one entered.
        if ($student->email && strtolower($student->email) !== $email) {
            throw ValidationException::withMessages(['email' => 'This email does not match the email on file for this Student ID. Please use that email or contact the Office of Student Services.']);
        }
        if (\App\Support\AccountEmail::takenByOther($email, 'student', $student->id)) {
            throw ValidationException::withMessages(['email' => \App\Support\AccountEmail::MESSAGE]);
        }
        // One code per minute per Student ID.
        if (!Cache::add('student-register:' . $student->student_id, 1, 60)) {
            throw ValidationException::withMessages(['email' => 'A one-time password was just sent. Please wait a minute before requesting another.']);
        }

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $student->forceFill([
            'email'                    => $student->email ?: $email,
            'password'                 => Hash::make($otp),
            'temp_password'            => null, // never kept in plain text
            'must_change_password'     => true,
            'temp_password_expires_at' => now()->addMinutes(self::OTP_MINUTES),
        ])->save();

        $student->notify(new RegistrationOtpNotification($otp, self::OTP_MINUTES));
        AuditLog::record('student_register', "Student {$student->student_id} registered and was sent a one-time password.", $student, [], [], $student);

        [$local, $domain] = explode('@', $email, 2) + [1 => ''];
        return response()->json([
            'message'      => 'A one-time password has been sent to your email.',
            'masked_email' => mb_substr($local, 0, 2) . str_repeat('*', max(1, mb_strlen($local) - 2)) . '@' . $domain,
            'expires_in'   => self::OTP_MINUTES,
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

    // B569: same "Active Sessions / Linked Devices" capability as the staff
    // side (AuthController::sessions()), for the student's own My Account.
    public function sessions(Request $request)
    {
        $student = $request->user('student');
        $current = $student->currentAccessToken();
        return response()->json(
            $student->tokens()
                ->orderByDesc('last_used_at')
                ->get(['id', 'name', 'created_at', 'last_used_at'])
                ->map(fn($t) => [
                    'id'           => $t->id,
                    'device'       => $t->name,
                    'created_at'   => $t->created_at,
                    'last_used_at' => $t->last_used_at,
                    'is_current'   => $current && (int) $t->id === (int) $current->id,
                ])
        );
    }

    public function revokeSession(Request $request, $tokenId)
    {
        $student = $request->user('student');
        $token = $student->tokens()->whereKey($tokenId)->first();
        abort_unless($token, 404, 'Session not found.');
        $wasCurrent = $student->currentAccessToken() && (int) $token->id === (int) $student->currentAccessToken()->id;
        $token->delete();
        AuditLog::record('logout', "Student {$student->student_id} signed out a linked device ({$token->name}).", $student);
        return response()->json(['message' => 'Session signed out.', 'was_current' => $wasCurrent]);
    }

    public function revokeOtherSessions(Request $request)
    {
        $student = $request->user('student');
        $current = $student->currentAccessToken();
        $student->tokens()->when($current, fn($q) => $q->whereKeyNot($current->id))->delete();
        AuditLog::record('logout', "Student {$student->student_id} signed out all other devices.", $student);
        return response()->json(['message' => 'Signed out of all other devices.']);
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'password'         => ['required', 'confirmed', 'max:64', 'different:current_password', \Illuminate\Validation\Rules\Password::min(8)->letters()->mixedCase()->numbers()->symbols()],
        ]);

        $student = $request->user('student');

        if (!Hash::check($request->current_password, $student->password)) {
            return response()->json(['message' => 'Current password is incorrect.'], 422);
        }

        $student->update([
            'password'              => Hash::make($request->password),
            'temp_password'         => null,
            'temp_password_expires_at' => null,
            'must_change_password'  => false,
        ]);
        \App\Support\PasswordReset::stamp($student, 'self');
        // The password is no longer temporary, so the "please change your password" bell
        // reminder from before is stale now - clear it, or it keeps showing on every login.
        $student->unreadNotifications()->where('type', ChangePasswordReminderNotification::class)->delete();
        AuditLog::record('password_change', "Student {$student->student_id} changed their password.", $student);
        $student->notify(new PasswordChangedNotification());

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
        // The same record My Account reads, so a changed program / year level shows on the banner at once.
        'student'               => $student->only(['student_id', 'first_name', 'last_name', 'college', 'program', 'year_level', 'section']),
        'term'                  => $this->currentTermLabel(),
    ]);
}

/** "AY 2026–2027 · 1st Semester" from the current term set in Management > School Calendar (null when none). */
private function currentTermLabel(): ?array
{
    $ay = \App\Models\AcademicYear::where('is_current', true)->first();
    if (! $ay) {
        return null;
    }
    $today = now('Asia/Manila')->toDateString();
    $in = fn ($from, $to) => $from && $to && $today >= substr((string) $from, 0, 10) && $today <= substr((string) $to, 0, 10);
    $sem = null;
    if ($in($ay->first_sem_start, $ay->first_sem_end)) {
        $sem = '1st Semester';
    } elseif ($in($ay->second_sem_start, $ay->second_sem_end)) {
        $sem = '2nd Semester';
    } elseif ($in($ay->midyear_start, $ay->midyear_end)) {
        $sem = 'Midyear';
    }
    return ['academic_year' => $ay->start_year . '–' . ($ay->start_year + 1), 'semester' => $sem];
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
        'email'          => ['nullable', 'email', new \App\Rules\CompleteEmail, new \App\Rules\UniqueAccountEmail('student', $student->id)],
        'contact_number' => ['nullable', 'string', 'max:11', 'regex:' . self::PHONE_REGEX],

        // Personal Information (Student Information Sheet)
        // Not today or later; under 15 is refused (15-17 is allowed, with a warning on the form, for accelerated students).
        'birthdate'      => 'nullable|date|before:today|after:1900-01-01|before_or_equal:-15 years',
        'sex'            => 'nullable|in:Male,Female',
        'civil_status'   => 'nullable|in:Single,Married,Divorced,Widowed,Separated',
        'nationality'    => ['nullable', 'string', 'max:100', 'regex:' . self::NAME_REGEX],
        'birthplace'     => ['nullable', 'string', 'max:255', 'regex:' . self::TEXT_REGEX],
        'languages'      => ['nullable', 'string', 'max:255', 'regex:' . self::TEXT_REGEX],
        'address'        => ['nullable', 'string', 'min:5', 'max:255', 'regex:' . self::TEXT_REGEX, 'regex:/\pL/u'],

        // Family Information - names split into first/middle/last, same as
        // the student's own name and the guardian_first_name/middle/last
        // columns, rather than one plain "name" string.
        'father_first_name'       => ['nullable', 'string', 'max:255', 'regex:' . self::NAME_REGEX],
        'father_middle_name'      => ['nullable', 'string', 'max:255', 'regex:' . self::NAME_REGEX],
        'father_last_name'        => ['nullable', 'string', 'max:255', 'regex:' . self::NAME_REGEX],
        'father_suffix'           => ['nullable', 'string', 'max:20', 'regex:' . self::NAME_REGEX],
        'father_occupation'       => ['nullable', 'string', 'max:255', 'regex:' . self::TEXT_REGEX],
        'father_contact_number'   => ['nullable', 'string', 'max:11', 'regex:' . self::PHONE_REGEX],
        'mother_first_name'       => ['nullable', 'string', 'max:255', 'regex:' . self::NAME_REGEX],
        'mother_middle_name'      => ['nullable', 'string', 'max:255', 'regex:' . self::NAME_REGEX],
        'mother_last_name'        => ['nullable', 'string', 'max:255', 'regex:' . self::NAME_REGEX],
        'mother_suffix'           => ['nullable', 'string', 'max:20', 'regex:' . self::NAME_REGEX],
        'mother_occupation'       => ['nullable', 'string', 'max:255', 'regex:' . self::TEXT_REGEX],
        'mother_contact_number'   => ['nullable', 'string', 'max:11', 'regex:' . self::PHONE_REGEX],
        // B522: a parent's age below what is biologically plausible is refused.
        'father_age'                      => ['nullable', 'string', 'max:3', 'regex:' . self::AGE_REGEX, function ($attr, $value, $fail) {
            if ($value !== null && $value !== '' && (int) $value < 15) $fail("The father's age seems too low. Please check it.");
        }],
        'father_educational_attainment'   => ['nullable', 'string', 'max:255', 'regex:' . self::TEXT_REGEX],
        'mother_age'                      => ['nullable', 'string', 'max:3', 'regex:' . self::AGE_REGEX, function ($attr, $value, $fail) {
            if ($value !== null && $value !== '' && (int) $value < 15) $fail("The mother's age seems too low. Please check it.");
        }],
        'mother_educational_attainment'   => ['nullable', 'string', 'max:255', 'regex:' . self::TEXT_REGEX],
        'guardian_first_name'             => ['nullable', 'string', 'max:255', 'regex:' . self::NAME_REGEX],
        'guardian_middle_name'            => ['nullable', 'string', 'max:255', 'regex:' . self::NAME_REGEX],
        'guardian_last_name'              => ['nullable', 'string', 'max:255', 'regex:' . self::NAME_REGEX],
        'guardian_suffix'                 => ['nullable', 'string', 'max:20', 'regex:' . self::NAME_REGEX],
        // B528: a guardian must be at least 25.
        'guardian_age'                    => ['nullable', 'string', 'max:3', 'regex:' . self::AGE_REGEX, function ($attr, $value, $fail) {
            if ($value !== null && $value !== '' && (int) $value < 25) $fail('Guardian must be at least 25 years old.');
        }],
        'guardian_occupation'             => ['nullable', 'string', 'max:255', 'regex:' . self::TEXT_REGEX],
        'guardian_educational_attainment' => ['nullable', 'string', 'max:255', 'regex:' . self::TEXT_REGEX],
        'guardian_contact'                => ['nullable', 'string', 'max:11', 'regex:' . self::PHONE_REGEX],

        // Siblings Information - a repeatable list filled in by the student,
        // same first/middle/last split per sibling.
        'siblings'                    => 'nullable|array',
        'siblings.*.first_name'       => ['required', 'string', 'max:255', 'regex:' . self::NAME_REGEX],
        'siblings.*.middle_name'      => ['nullable', 'string', 'max:255', 'regex:' . self::NAME_REGEX],
        'siblings.*.last_name'        => ['required', 'string', 'max:255', 'regex:' . self::NAME_REGEX],
        'siblings.*.suffix'           => ['nullable', 'string', 'max:20', 'regex:' . self::NAME_REGEX],
        'siblings.*.age'              => ['nullable', 'string', 'max:3', 'regex:' . self::AGE_REGEX],
        'siblings.*.occupation'       => ['nullable', 'string', 'max:255', 'regex:' . self::TEXT_REGEX],
        'siblings.*.educational_attainment' => ['nullable', 'string', 'max:255', 'regex:' . self::TEXT_REGEX],
        'siblings.*.civil_status'     => 'nullable|in:Single,Married,Divorced,Widowed,Separated',

        // Educational Attainment (school history)
        'elementary_school'               => ['nullable', 'string', 'max:255', 'regex:' . self::TEXT_REGEX, 'regex:/\pL/u'],
        'elementary_year_graduated'       => ['nullable', 'string', 'regex:' . self::YEAR_REGEX, 'integer', 'min:1950', 'max:' . date('Y')],
        'high_school'                     => ['nullable', 'string', 'max:255', 'regex:' . self::TEXT_REGEX, 'regex:/\pL/u'],
        'high_school_year_graduated'      => ['nullable', 'string', 'regex:' . self::YEAR_REGEX, 'integer', 'min:1950', 'max:' . date('Y')],
        'college_school'                  => ['nullable', 'string', 'max:255', 'regex:' . self::TEXT_REGEX, 'regex:/\pL/u'],
        'college_year_graduated'          => ['nullable', 'string', 'regex:' . self::YEAR_REGEX, 'integer', 'min:1950', 'max:' . date('Y')],
        'senior_high_school'              => ['nullable', 'string', 'max:255', 'regex:' . self::TEXT_REGEX, 'regex:/\pL/u'],
        'senior_high_year_graduated'      => ['nullable', 'string', 'regex:' . self::YEAR_REGEX, 'integer', 'min:1950', 'max:' . date('Y')],
        'senior_high_achievements'        => ['nullable', 'string', 'max:1000', 'regex:/\pL/u'],
        'high_school_achievements'        => ['nullable', 'string', 'max:1000', 'regex:/\pL/u'],
        'elementary_achievements'         => ['nullable', 'string', 'max:1000', 'regex:/\pL/u'],
    ]);

    // Bug: Elementary, Junior High, Senior High and College year graduated
    // were each validated on their own (any 1950-to-present year), so a
    // student could save the exact same year for every level, or an
    // earlier level finishing after a later one, with no warning. Each
    // level now has to be no earlier than the one before it - checked
    // against whichever value (submitted this request, or already on the
    // account) is current for that level, since the Educational Background
    // tab can save with any subset of these fields present.
    $educationLevels = [
        'elementary_year_graduated'  => 'Elementary',
        'high_school_year_graduated' => 'Junior High School',
        'senior_high_year_graduated' => 'Senior High School',
        'college_year_graduated'     => 'College',
    ];
    $prevKey = null;
    $prevLabel = null;
    foreach ($educationLevels as $key => $label) {
        $value = array_key_exists($key, $validated) ? $validated[$key] : $student->$key;
        if ($prevKey !== null && $value) {
            $prevValue = array_key_exists($prevKey, $validated) ? $validated[$prevKey] : $student->$prevKey;
            if ($prevValue && (int) $value <= (int) $prevValue) {
                return response()->json([
                    'message' => "{$label} year graduated must be after {$prevLabel}'s.",
                    'errors'  => [$key => ["{$label} year graduated must be after {$prevLabel}'s."]],
                ], 422);
            }
        }
        $prevKey = $key;
        $prevLabel = $label;
    }

    // The father's and mother's contact numbers cannot be the same number.
    $fatherNo = $validated['father_contact_number'] ?? $student->father_contact_number;
    $motherNo = $validated['mother_contact_number'] ?? $student->mother_contact_number;
    if ($fatherNo && $motherNo && $fatherNo === $motherNo) {
        $field = array_key_exists('mother_contact_number', $validated) ? 'mother_contact_number' : 'father_contact_number';
        return response()->json([
            'message' => "The father's and mother's contact numbers are the same. Please enter a different number for each parent.",
            'errors'  => [$field => ["The father's and mother's contact numbers are the same. Please enter a different number for each parent."]],
        ], 422);
    }

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

// A student refers themselves to GCU from the student portal. It becomes an
// ordinary referral (same case file rules as ReferralController::store()),
// marked as a self-referral with no staff referrer.
public function referSelf(Request $request)
{
    $student = $request->user('student');
    $validated = $request->validate([
        'referral_type'     => 'required|in:counseling,class_attendance,academic_deficiency,leave_of_absence,withdrawal,readmission,shifting',
        'nature_of_concern' => 'required|string|min:10|max:1000',
    ], [
        'nature_of_concern.min' => 'Please describe your concern in at least 10 characters.',
    ]);

    $waiting = $student->referrals()->where('is_self_referred', true)->where('status', 'submitted')->exists();
    abort_if($waiting, 422, 'You already have a request waiting for GCU. Please wait until it is acknowledged.');

    $case = \App\Models\CaseFile::where('student_id', $student->id)->first();
    $isExisting = (bool) $case;
    if (!$case) {
        $case = \App\Models\CaseFile::create([
            'student_id'   => $student->id,
            'case_type'    => $validated['referral_type'],
            'current_unit' => 'GCU',
            'status'       => 'open',
            'opened_date'  => today(),
        ]);
    } elseif (!$case->isOpen()) {
        $case->update(['status' => 'open', 'closed_date' => null]);
    }

    $name = trim($student->first_name . ' ' . $student->last_name);
    $referral = \App\Models\Referral::create([
        ...$validated,
        'student_id'          => $student->id,
        'case_id'             => $case->id,
        'referred_by_user_id' => null,
        'referrer_name'       => $name,
        'referrer_role'       => 'student',
        'referrer_source'     => 'self',
        'referrer_college'    => $student->college,
        'is_self_referred'    => true,
        'status'              => 'submitted',
    ]);

    AuditLog::record('created', "Student {$student->student_id} referred themselves ({$referral->referral_code}).", $referral);

    $recipients = \App\Models\User::whereIn('role', ['admin', 'gcu_staff'])->where('is_active', true)->get();
    \Illuminate\Support\Facades\Notification::send($recipients, new \App\Notifications\NewReferralNotification($referral));
    if (!$isExisting) {
        $student->notify(new \App\Notifications\FillOutFormsNotification());
    }

    return response()->json($referral, 201);
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