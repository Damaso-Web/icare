<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Notifications\ChangePasswordReminderNotification;
use App\Notifications\PasswordChangedNotification;
use App\Notifications\RegistrationOtpNotification;
use Illuminate\Validation\ValidationException;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    // One generic message for every credential failure, so the login form can't be
    // used to discover which emails have accounts. The real reason goes to the audit log.
    private const BAD_LOGIN = 'Invalid email or password.';

    // Which sign-in card (Login Choice) each role belongs under. Every staff
    // role is in exactly one of these two groups.
    private const FACULTY_AUDIENCE_ROLES = ['faculty', 'dept_chair', 'dean', 'dean_secretary'];
    private const OSS_AUDIENCE_ROLES     = ['admin', 'gcu_staff', 'sdu_head', 'tmdu_staff', 'tmdu_head'];

    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email|max:255',
            'password' => 'required|string|max:255',
            // Which sign-in card was used ('faculty' / 'oss'); optional so a
            // bare /login (no audience known) still works.
            'audience' => 'nullable|in:faculty,oss',
        ]);

        // Too many wrong passwords in a row locks this email for a few minutes.
        $guard = new \App\Support\LoginGuard('staff', $request->email);
        if ($minutes = $guard->lockedMinutes()) {
            AuditLog::record('login_locked', "Staff sign-in refused for {$request->email}: locked after repeated incorrect attempts.");
            return response()->json(['message' => \App\Support\LoginGuard::lockedMessage($minutes), 'locked' => true], 429);
        }

        $user = User::where('email', $request->email)->first();

        // Always run a hash check so response time doesn't reveal whether the email exists.
        $hashOk = Hash::check($request->password, $user->password ?? '$2y$10$2GvViDLZ9R65EGg.7Rovhu/4d61pXs3jA97oN/8FQRJm0fnXvRIEW');

        if (!$user) {
            AuditLog::record('login_failed', "Failed staff login: no account with email {$request->email}.");
            $left = $guard->fail();
            return response()->json(['message' => self::BAD_LOGIN . \App\Support\LoginGuard::attemptsMessage($left), 'attempts_left' => $left], 401);
        }

        if (!$hashOk) {
            $left = $guard->fail();
            AuditLog::record('login_failed', "Failed staff login for {$user->name}: incorrect password" . ($left === 0 ? ' - sign-in locked for ' . \App\Support\LoginGuard::LOCK_MINUTES . ' minutes.' : " ({$left} attempt(s) left)."), $user, [], [], $user);
            return response()->json(['message' => self::BAD_LOGIN . \App\Support\LoginGuard::attemptsMessage($left), 'attempts_left' => $left], 401);
        }

        // Right card, wrong audience: the password was correct, so this isn't
        // a credential failure - don't count it against the lockout guard,
        // just point them at the sign-in card their account actually belongs
        // under. (No audience sent - a bare /login - skips this check.)
        if ($request->audience === 'faculty' && !in_array($user->role, self::FACULTY_AUDIENCE_ROLES, true)) {
            AuditLog::record('login_failed', "Staff login refused for {$user->name}: {$user->role} account used the Faculty sign-in.", $user, [], [], $user);
            return response()->json(['message' => 'This is an OSS Personnel account. Please use the OSS Personnel sign-in instead.'], 401);
        }
        if ($request->audience === 'oss' && !in_array($user->role, self::OSS_AUDIENCE_ROLES, true)) {
            AuditLog::record('login_failed', "Staff login refused for {$user->name}: {$user->role} account used the OSS Personnel sign-in.", $user, [], [], $user);
            return response()->json(['message' => 'This is a Faculty account. Please use the Faculty sign-in instead.'], 401);
        }

        // A one-time password (from registering) works only for a short time.
        if ($user->temp_password_expires_at && $user->temp_password_expires_at->isPast()) {
            return response()->json(['message' => 'Your one-time password has expired. Please register again to get a new one.'], 401);
        }

        // Only reveal the deactivated state AFTER a correct password.
        if (!$user->is_active) {
            AuditLog::record('login_failed', "Failed staff login for {$user->name}: account is deactivated.", $user, [], [], $user);
            return response()->json(['message' => 'This account has been deactivated. Please contact the OSS administrator.'], 401);
        }

        $guard->clear();

        // Optional 2nd step: 6-digit code emailed to the registered address.
        if (config('security.otp_enabled')) {
            return $this->sendOtp($user);
        }

        return $this->issueToken($user, $request);
    }

    private function sendOtp(User $user)
    {
        $code     = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $otpToken = Str::random(48);

        Cache::put("staff_otp:$otpToken", [
            'user_id'  => $user->id,
            'hash'     => Hash::make($code),
            'attempts' => 0,
        ], now()->addMinutes(config('security.otp_ttl')));

        try {
            Mail::raw(
                "Your iCARE verification code is {$code}.\n\nIt expires in " . config('security.otp_ttl') . " minutes. If you did not try to log in, change your password and tell the OSS administrator.",
                fn ($m) => $m->to($user->email)->subject('iCARE login verification code')
            );
        } catch (\Throwable $e) {
            Cache::forget("staff_otp:$otpToken");
            Log::error('OTP email failed: ' . $e->getMessage());
            return response()->json(['message' => 'We could not send the verification code. Please try again or contact the OSS administrator.'], 503);
        }

        AuditLog::record('login_otp_sent', "Login verification code sent to {$user->name}.", $user, [], [], $user);

        return response()->json([
            'otp_required' => true,
            'otp_token'    => $otpToken,
            'email_hint'   => preg_replace('/(?<=.{2}).(?=[^@]*@)/', '*', $user->email),
        ]);
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'otp_token' => 'required|string|max:100',
            'otp'       => 'required|digits:6',
        ]);

        $key  = 'staff_otp:' . $request->otp_token;
        $data = Cache::get($key);

        if (!$data) {
            return response()->json(['message' => 'This code has expired. Please log in again.'], 422);
        }

        if (!Hash::check($request->otp, $data['hash'])) {
            $data['attempts']++;
            if ($data['attempts'] >= config('security.otp_attempts')) {
                Cache::forget($key);
                return response()->json(['message' => 'Too many wrong codes. Please log in again.'], 422);
            }
            Cache::put($key, $data, now()->addMinutes(config('security.otp_ttl')));
            return response()->json(['message' => 'Incorrect verification code.'], 422);
        }

        Cache::forget($key); // single use
        $user = User::where('id', $data['user_id'])->where('is_active', true)->first();

        if (!$user) {
            return response()->json(['message' => self::BAD_LOGIN], 401);
        }

        return $this->issueToken($user, $request);
    }

    private function issueToken(User $user, Request $request)
    {
        $user->update(['last_login_at' => now()]);
        // B569: label the token with the device/browser it was issued to,
        // so "Active Sessions" (see sessions()) shows something recognizable.
        $token = $user->createToken(\App\Support\DeviceLabel::fromRequest($request))->plainTextToken;

        // Still on a temporary / one-time password: remind them in the bell (once, until read).
        if ($user->must_change_password && !$user->unreadNotifications()->where('type', ChangePasswordReminderNotification::class)->exists()) {
            $user->notify(new ChangePasswordReminderNotification());
        }

        // Nobody is authenticated yet during login, so name the user explicitly.
        AuditLog::record('login', "User {$user->name} logged in.", $user, [], [], $user);

        return response()->json([
            'token' => $token,
            'user'  => $user->only([
                'id', 'name', 'first_name', 'middle_name', 'last_name',
                'email', 'role', 'unit', 'college', 'department',
                'contact_number', 'employee_id', 'must_change_password'
            ]),
        ]);
    }

    // First-time sign-up for faculty and deans: the Employee ID must be in the
    // list the GCU uploaded, and the email must be the BSU email on file for it.
    // A one-time password is sent to that email and works as the password until
    // it is changed.
    public const OTP_MINUTES = 30;
    private const SELF_REGISTER_ROLES = ['faculty', 'dept_chair', 'dean', 'dean_secretary'];

    public function register(Request $request)
    {
        $data = $request->validate([
            'employee_id'      => 'required|string|max:50',
            'email'            => 'required|email|max:255',
            'consent_accepted' => 'required|accepted',
        ]);
        $email = strtolower(trim($data['email']));

        // TEMPORARY (for testing the OTP email flow with a non-BSU inbox):
        // the @bsu.edu.ph domain check is disabled below. Re-enable it before
        // the real demo/deployment by uncommenting this block.
        // if (!str_ends_with($email, '@bsu.edu.ph')) {
        //     throw ValidationException::withMessages(['email' => 'Please enter your BSU email address (it must end in @bsu.edu.ph).']);
        // }

        $user = User::where('employee_id', trim($data['employee_id']))->first();
        if (!$user) {
            throw ValidationException::withMessages(['employee_id' => 'Employee not found. Please contact your College (the Dean\'s office) so you can be added to the faculty list. Deans and unit staff should contact their unit head or the OSS administrator.']);
        }
        if (!in_array($user->role, self::SELF_REGISTER_ROLES, true)) {
            throw ValidationException::withMessages(['employee_id' => 'Registration here is for faculty and deans only. Please contact the OSS administrator.']);
        }
        if (!$user->is_active) {
            throw ValidationException::withMessages(['employee_id' => 'This account is not active. Please contact the OSS administrator.']);
        }
        if (!$user->must_change_password) {
            throw ValidationException::withMessages(['employee_id' => 'This Employee ID already has an account. Please sign in, or use "Forgot password?" if you cannot remember your password.']);
        }
        // The code goes only to the email on file for this employee.
        if (strtolower((string) $user->email) !== $email) {
            throw ValidationException::withMessages(['email' => 'This email does not match the email on file for this Employee ID. Please use that email or contact the OSS administrator.']);
        }
        if (!Cache::add('staff-register:' . $user->id, 1, 60)) {
            throw ValidationException::withMessages(['email' => 'A one-time password was just sent. Please wait a minute before requesting another.']);
        }

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $user->forceFill([
            'password'                 => Hash::make($otp),
            'temp_password'            => null, // never kept in plain text
            'must_change_password'     => true,
            'temp_password_expires_at' => now()->addMinutes(self::OTP_MINUTES),
        ])->save();

        $user->notify(new RegistrationOtpNotification($otp, self::OTP_MINUTES, 'email address'));
        AuditLog::record('staff_register', "{$user->name} registered and was sent a one-time password.", $user, [], [], $user);

        [$local, $domain] = explode('@', $email, 2) + [1 => ''];
        return response()->json([
            'message'      => 'A one-time password has been sent to your email.',
            'masked_email' => mb_substr($local, 0, 2) . str_repeat('*', max(1, mb_strlen($local) - 2)) . '@' . $domain,
            'expires_in'   => self::OTP_MINUTES,
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

    // B569: "Active Sessions / Linked Devices" - lets an account see every
    // device currently signed in and sign any of them out individually,
    // without forcing a destructive single-session-only policy on everyone.
    public function sessions(Request $request)
    {
        $current = $request->user()->currentAccessToken();
        return response()->json(
            $request->user()->tokens()
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
        $token = $request->user()->tokens()->whereKey($tokenId)->first();
        abort_unless($token, 404, 'Session not found.');
        $wasCurrent = $request->user()->currentAccessToken() && (int) $token->id === (int) $request->user()->currentAccessToken()->id;
        $token->delete();
        AuditLog::record('logout', "User {$request->user()->name} signed out a linked device ({$token->name}).");
        return response()->json(['message' => 'Session signed out.', 'was_current' => $wasCurrent]);
    }

    public function revokeOtherSessions(Request $request)
    {
        $current = $request->user()->currentAccessToken();
        $request->user()->tokens()->when($current, fn($q) => $q->whereKeyNot($current->id))->delete();
        AuditLog::record('logout', "User {$request->user()->name} signed out all other devices.");
        return response()->json(['message' => 'Signed out of all other devices.']);
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'password'         => ['required', 'confirmed', 'max:64', 'different:current_password', Password::min(8)->letters()->mixedCase()->numbers()->symbols()],
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'Current password is incorrect.'], 422);
        }

        $user->update([
            'password'            => Hash::make($request->password),
            'temp_password'       => null,
            'temp_password_expires_at' => null,
            'must_change_password'=> false,
        ]);
        \App\Support\PasswordReset::stamp($user, 'self');
        $user->tokens()->where('id', '!=', $request->user()->currentAccessToken()->id ?? 0)->delete();
        // The password is no longer temporary, so the "please change your password" bell
        // reminder from before is stale now - clear it, or it keeps showing on every login.
        $user->unreadNotifications()->where('type', ChangePasswordReminderNotification::class)->delete();
        AuditLog::record('password_change', "User {$user->name} changed their password.");
        $user->notify(new PasswordChangedNotification());

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
            'email'            => ['required', 'email', 'regex:/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/', 'max:255', new \App\Rules\UniqueAccountEmail('staff', $user->id)],
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