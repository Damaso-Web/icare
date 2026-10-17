<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules\Password as PasswordRule;

// "Forgot password" by email link, for both staff accounts (users table) and
// student accounts (students table).
class PasswordResetController extends Controller
{
    private function broker(string $type)
    {
        return Password::broker($type === 'student' ? 'students' : 'users');
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

        $broker = $this->broker($data['type']);
        $user = $broker->getUser(['email' => $email]);
        if (!$user) {
            throw ValidationException::withMessages(['email' => $isStudent
                ? 'This email does not match the email registered to any student account. Please use the email you gave the Office of Student Services.'
                : 'No personnel account is registered with this email address.']);
        }
        if (($user->is_active ?? true) === false) {
            throw ValidationException::withMessages(['email' => 'This account is not active. Please contact the Office of Student Services.']);
        }

        try {
            // The link goes only to the address on the verified account.
            $status = $broker->sendResetLink(['email' => $user->email]);
            if ($status === Password::RESET_THROTTLED) {
                throw ValidationException::withMessages(['email' => 'A reset link was just sent. Please wait a minute before requesting another.']);
            }
            if ($status !== Password::RESET_LINK_SENT) {
                Log::warning("Forgot-password: no link sent for {$email} ({$status}).");
            }
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Forgot-password email failed: ' . $e->getMessage());
        }

        return response()->json(['message' => 'A password reset link has been sent to your email.']);
    }

    public function reset(Request $request)
    {
        $data = $request->validate([
            'token'    => 'required|string',
            'email'    => 'required|email|max:255',
            'type'     => 'required|in:staff,student',
            'password' => ['required', 'confirmed', 'max:64', PasswordRule::min(8)->letters()->mixedCase()->numbers()->symbols()],
        ]);

        $status = $this->broker($data['type'])->reset(
            ['email' => $data['email'], 'password' => $data['password'], 'password_confirmation' => $request->password_confirmation, 'token' => $data['token']],
            function ($user, $password) use ($data) {
                $user->forceFill([
                    'password'             => Hash::make($password),
                    'must_change_password' => false,
                    'temp_password'        => null,
                ])->save();
                $user->tokens()->delete(); // sign out every device
                event(new PasswordReset($user));
                AuditLog::record('password_reset', "Password reset by email link for {$data['email']}.", $user, [], [], $user);
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json(['message' => 'This reset link is invalid or has expired. Please request a new one.'], 422);
        }

        return response()->json(['message' => 'Your password has been reset. You can now sign in.']);
    }
}