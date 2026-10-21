<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Student;
use App\Models\User;
use App\Notifications\PasswordResetOtpNotification;
use App\Support\LoginGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules\Password as PasswordRule;

// "Forgot password" with a one-time code (OTP) emailed to the account, for both
// staff accounts (users table) and student accounts (students table). No reset
// links are sent, so there is nothing to click.
class PasswordResetController extends Controller
{
    public const OTP_MINUTES  = 10;   // how long a code works
    public const MAX_ATTEMPTS = 5;    // wrong codes allowed before a new one is needed
    public const RESEND_SECONDS = 60; // one code per minute per account

    private function account(string $type, string $email)
    {
        return $type === 'student'
            ? Student::where('email', $email)->first()
            : User::where('email', $email)->first();
    }

    public function forgot(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email|max:255',
            'type'  => 'required|in:staff,student',
        ]);

        $email = strtolower(trim($data['email']));
        $isStudent = $data['type'] === 'student';

        // Check the address against the registered accounts first, and say
        // clearly why a reset can't go ahead (nothing is sent in these cases).
        if (!$isStudent && !str_ends_with($email, '@bsu.edu.ph')) {
            throw ValidationException::withMessages(['email' => 'Please enter your BSU email address (it must end in @bsu.edu.ph).']);
        }

        $user = $this->account($data['type'], $email);
        if (!$user) {
            throw ValidationException::withMessages(['email' => $isStudent
                ? 'This email does not match the email registered to any student account. Please use the email you gave the Office of Student Services.'
                : 'No personnel account is registered with this email address.']);
        }
        if (($user->is_active ?? true) === false) {
            throw ValidationException::withMessages(['email' => 'This account is not active. Please contact the Office of Student Services.']);
        }

        $existing = DB::table('password_reset_otps')->where('type', $data['type'])->where('email', $email)->first();
        if ($existing && now()->diffInSeconds($existing->sent_at, true) < self::RESEND_SECONDS && now()->lt($existing->expires_at)) {
            throw ValidationException::withMessages(['email' => 'A code was just sent. Please wait a minute before requesting another.']);
        }

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        DB::table('password_reset_otps')->updateOrInsert(
            ['type' => $data['type'], 'email' => $email],
            [
                'code_hash'  => Hash::make($otp),
                'attempts'   => 0,
                'expires_at' => now()->addMinutes(self::OTP_MINUTES),
                'sent_at'    => now(),
                'created_at' => $existing->created_at ?? now(),
                'updated_at' => now(),
            ]
        );

        try {
            // The code goes only to the address on the verified account.
            $user->notify(new PasswordResetOtpNotification($otp, self::OTP_MINUTES));
        } catch (\Throwable $e) {
            Log::error('Forgot-password code email failed: ' . $e->getMessage());
        }

        AuditLog::record('password_reset_requested', "Password reset code requested for {$email}.", $user, [], [], $user);

        return response()->json([
            'message' => 'A 6-digit code has been sent to your email.',
            'expires_in_minutes' => self::OTP_MINUTES,
        ]);
    }

    public function reset(Request $request)
    {
        $data = $request->validate([
            'otp'      => ['required', 'digits:6'],
            'email'    => 'required|email|max:255',
            'type'     => 'required|in:staff,student',
            'password' => ['required', 'confirmed', 'max:64', PasswordRule::min(8)->letters()->mixedCase()->numbers()->symbols()],
        ], ['otp.digits' => 'Enter the 6-digit code from your email.']);

        $email = strtolower(trim($data['email']));
        $row = DB::table('password_reset_otps')->where('type', $data['type'])->where('email', $email)->first();
        $user = $this->account($data['type'], $email);

        $bad = fn (string $msg) => throw ValidationException::withMessages(['otp' => $msg]);

        if (!$row || !$user || now()->gte($row->expires_at)) {
            if ($row) DB::table('password_reset_otps')->where('id', $row->id)->delete();
            $bad('This code is invalid or has expired. Please request a new one.');
        }
        if (($user->is_active ?? true) === false) {
            $bad('This account is not active. Please contact the Office of Student Services.');
        }
        if ($row->attempts >= self::MAX_ATTEMPTS) {
            DB::table('password_reset_otps')->where('id', $row->id)->delete();
            $bad('Too many wrong codes. Please request a new one.');
        }

        if (!Hash::check($data['otp'], $row->code_hash)) {
            $used = $row->attempts + 1;
            if ($used >= self::MAX_ATTEMPTS) {
                DB::table('password_reset_otps')->where('id', $row->id)->delete();
                AuditLog::record('password_reset_failed', "Password reset code for {$email} entered wrong " . self::MAX_ATTEMPTS . ' times; code cancelled.', $user, [], [], $user);
                $bad('Too many wrong codes. Please request a new one.');
            }
            DB::table('password_reset_otps')->where('id', $row->id)->update(['attempts' => $used]);
            $left = self::MAX_ATTEMPTS - $used;
            $bad("That code is not correct. {$left} " . ($left === 1 ? 'try' : 'tries') . ' left.');
        }

        $user->forceFill([
            'password'                 => Hash::make($data['password']),
            'must_change_password'     => false,
            'temp_password'            => null,
            'temp_password_expires_at' => null,
        ])->save();
        \App\Support\PasswordReset::stamp($user, 'email_otp');
        $user->tokens()->delete(); // sign out every device
        DB::table('password_reset_otps')->where('id', $row->id)->delete();
        (new LoginGuard($data['type'] === 'student' ? 'student' : 'staff', $data['type'] === 'student' ? $user->student_id : $email))->clear();
        AuditLog::record('password_reset', "Password reset with an emailed code for {$email}.", $user, [], [], $user);

        return response()->json(['message' => 'Your password has been reset. You can now sign in.']);
    }
}