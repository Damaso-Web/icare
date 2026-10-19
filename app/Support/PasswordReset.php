<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Notifications\AdminPasswordResetNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Administrator-initiated password resets for students and employees: a reason
 * from a fixed list (checked on the server), a temporary password, sign-out of
 * every session, a forced change at the next login, an email notice and an
 * Audit Log entry. Also remembers who/when/how a password last changed.
 */
class PasswordReset
{
    /** Keep in sync with PASSWORD_RESET_REASONS in resources/js/constants/accountReasons.js */
    public const STUDENT_REASONS = [
        'no_email_access'      => 'Student cannot access their registered email',
        'link_not_received'    => 'Reset link expired or was not received',
        'link_not_working'     => 'Reset link is not working',
        'unauthorized_access'  => 'Suspected unauthorized access to the account',
        'identity_verified'    => 'Identity verified in person at the office',
        'other'                => 'Other',
    ];

    public const EMPLOYEE_REASONS = [
        'forgot_password'      => 'Employee forgot password',
        'locked_out'           => 'Account locked out',
        'compromised'          => 'Suspected compromised account',
        'new_account'          => 'New account setup',
        'other'                => 'Other',
    ];

    public static function reasons(string $kind): array
    {
        return $kind === 'student' ? self::STUDENT_REASONS : self::EMPLOYEE_REASONS;
    }

    /** Stamp "password changed" details on an account (call after the password is saved). */
    public static function stamp($account, string $source, ?string $byName = null): void
    {
        $account->forceFill([
            'password_changed_at'       => now(),
            'password_change_source'    => $source,
            'password_changed_by_name'  => $byName,
        ])->save();
    }

    /** What the Reset Password dialog shows about the last change. */
    public static function info($account): array
    {
        $at = $account->password_changed_at;
        $how = match ($account->password_change_source) {
            'self'        => 'changed by the account owner',
            'email_link'  => 'reset by the account owner through the email link',
            'admin_reset' => 'reset by an administrator' . ($account->password_changed_by_name ? ' (' . $account->password_changed_by_name . ')' : ''),
            'registration'=> 'set through first-time registration',
            default       => null,
        };
        return [
            'last_changed_at' => $at?->toIso8601String(),
            'how'             => $how,
            'by'              => $account->password_change_source === 'admin_reset' ? $account->password_changed_by_name : null,
            'within_hour'     => $at ? $at->gt(now()->subHour()) : false,
        ];
    }

    /** Validates the reason + note (reason must belong to this record type). Returns [key, label, note]. */
    public static function validateReason(Request $request, string $kind): array
    {
        $reasons = self::reasons($kind);
        $request->validate([
            'reason' => ['required', Rule::in(array_keys($reasons))],
            'note'   => ['nullable', 'string', 'max:500', 'required_if:reason,other', 'regex:/\p{L}/u'],
        ], [
            'reason.required'  => 'Please select a reason for the reset.',
            'reason.in'        => 'That reason is not allowed for this action.',
            'note.required_if' => 'Please describe the reason when "Other" is selected.',
            'note.regex'       => 'Please explain in words, not numbers only.',
        ]);
        $note = trim((string) $request->input('note', ''));
        return [$request->input('reason'), $reasons[$request->input('reason')], $note === '' ? null : $note];
    }

    /**
     * Performs the reset. $kind is 'student' or 'employee'.
     * Returns the temporary password (shown once to the administrator).
     */
    public static function perform(Request $request, $account, string $kind): string
    {
        [, $label, $note] = self::validateReason($request, $kind);
        $actor = $request->user();
        $who = $kind === 'student' ? "student {$account->student_id}" : $account->name;
        $before = self::info($account);

        $temp = Str::random(10);
        $account->forceFill([
            'password'                 => Hash::make($temp),
            'temp_password'            => $temp,
            'must_change_password'     => true,
            'temp_password_expires_at' => null,
        ])->save();
        self::stamp($account, 'admin_reset', $actor->name);

        // Sign out every active session; they must use the temporary password and change it.
        $account->tokens()->delete();

        $emailed = false;
        try {
            if ($account->email) {
                $account->notify(new AdminPasswordResetNotification($actor->name));
                $emailed = true;
            }
        } catch (\Throwable $e) {
            Log::warning('Admin password-reset notice failed: ' . $e->getMessage());
        }

        AuditLog::record(
            'password_reset',
            "Administrator {$actor->name} reset the password of {$who}. Reason: {$label}." . ($note ? " Note: {$note}." : '')
                . ($before['within_hour'] ? ' (Password had been changed within the last hour.)' : '')
                . ' Outcome: success; all sessions signed out; password change required at next login; email notice ' . ($emailed ? 'sent' : 'not sent') . '.',
            $account,
            ['password_last_changed' => $before['last_changed_at']],
            ['reason' => $label, 'note' => $note, 'sessions_signed_out' => true, 'email_notice_sent' => $emailed]
        );

        return $temp;
    }
}