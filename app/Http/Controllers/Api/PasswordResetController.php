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

        // Same answer whether or not the email exists, so this can't be used
        // to find out which emails have accounts.
        $generic = ['message' => 'If an account with that email exists, a password reset link has been sent to it.'];

        try {
            $broker = $this->broker($data['type']);
            $user = $broker->getUser(['email' => $data['email']]);
            if (!$user || ($user->is_active ?? true) === false) {
                return response()->json($generic);
            }
            $status = $broker->sendResetLink(['email' => $data['email']]);
            if ($status !== Password::RESET_LINK_SENT) {
                Log::warning("Forgot-password: no link sent for {$data['email']} ({$status}).");
            }
        } catch (\Throwable $e) {
            // Still answer with the generic message (so emails can't be probed),
            // but leave the real reason in the Render logs.
            Log::error('Forgot-password email failed: ' . $e->getMessage());
        }

        return response()->json($generic);
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