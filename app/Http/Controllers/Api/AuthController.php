<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Notifications\PasswordChangedNotification;
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

    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email|max:255',
            'password' => 'required|string|max:255',
        ]);

        $user = User::where('email', $request->email)->first();

        // Always run a hash check so response time doesn't reveal whether the email exists.
        $hashOk = Hash::check($request->password, $user->password ?? '$2y$10$2GvViDLZ9R65EGg.7Rovhu/4d61pXs3jA97oN/8FQRJm0fnXvRIEW');

        if (!$user) {
            AuditLog::record('login_failed', "Failed staff login: no account with email {$request->email}.");
            return response()->json(['message' => self::BAD_LOGIN], 401);
        }

        if (!$hashOk) {
            AuditLog::record('login_failed', "Failed staff login for {$user->name}: incorrect password.", $user, [], [], $user);
            return response()->json(['message' => self::BAD_LOGIN], 401);
        }

        // Only reveal the deactivated state AFTER a correct password.
        if (!$user->is_active) {
            AuditLog::record('login_failed', "Failed staff login for {$user->name}: account is deactivated.", $user, [], [], $user);
            return response()->json(['message' => 'This account has been deactivated. Please contact the OSS administrator.'], 401);
        }

        // Optional 2nd step: 6-digit code emailed to the registered address.
        if (config('security.otp_enabled')) {
            return $this->sendOtp($user);
        }

        return $this->issueToken($user);
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

        return $this->issueToken($user);
    }

    private function issueToken(User $user)
    {
        $user->update(['last_login_at' => now()]);
        $token = $user->createToken('icare-token')->plainTextToken;

        // Nobody is authenticated yet during login, so name the user explicitly.
        AuditLog::record('login', "User {$user->name} logged in.", $user, [], [], $user);

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
            'password'         => ['required', 'confirmed', Password::min(8)->letters()->mixedCase()->numbers()->symbols()],
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