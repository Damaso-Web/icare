<?php

namespace App\Support;

/**
 * Reasons an admin must pick when deactivating or reactivating a student
 * account. Keys are what the API accepts; labels and descriptions are shown to
 * the admin and written to the Audit Trail.
 */
class StudentAccountReasons
{
    public const DEACTIVATE = [
        'withdrawn_dropped'       => ['Withdrawn/Dropped', 'The student has officially withdrawn or dropped from the university and no longer needs access to the system. The account is deactivated and retained for record purposes.'],
        'leave_of_absence'        => ['Leave of Absence (LOA)', 'The student is on an approved leave of absence for the current term. The account is temporarily deactivated and can be reactivated when the student returns.'],
        'disciplinary_suspension' => ['Disciplinary Suspension', "The student's access is deactivated for the duration of an official suspension. The account can be reactivated once the suspension period ends."],
        'graduated'               => ['Graduated', 'The student has completed the program and no longer needs access to the system. The account is deactivated, and the records are retained for reference.'],
        'transferred'             => ['Transferred to Another School', "The student has transferred out of the university. The account is deactivated, and the student's case history is retained."],
        'security_concern'        => ['Security Concern', "The account is deactivated because of suspected unauthorized access or unusual activity. It remains deactivated until the student's identity and account security are verified."],
        'inactive_account'        => ['Inactive Account', 'The account has had no activity for an extended period. It is deactivated for security purposes and can be reactivated upon request.'],
        'requested_by_student'    => ['Requested by Student', 'The student has requested account deactivation. The request is recorded, and the account can be reactivated if the student requests it.'],
        'other'                   => ['Other', ''],
    ];

    /** Old key from before the nine-reason list; still shown correctly for accounts deactivated with it. */
    public const LEGACY = [
        'no_longer_enrolled' => 'No Longer Enrolled',
    ];

    public const REACTIVATE = [
        're_enrolled'          => ['Re-enrolled', 'The student has re-enrolled in the university. The account is reactivated, and previous records are retained.'],
        'returned_loa'         => ['Returned from Leave of Absence (LOA)', 'The student has returned from an approved leave of absence and is enrolled for the current term. The account is reactivated.'],
        'suspension_completed' => ['Suspension Completed', "The student's suspension period has ended. The account is reactivated, and access is restored."],
        'security_verified'    => ['Security Verified', "The student's identity and account security have been verified. The account is reactivated, and a password reset is required at the next login."],
        'reviewed_cleared'     => ['Reviewed and Cleared', "The inactive account has been reviewed and cleared for use at the student's request. The account is reactivated."],
        'requested_by_student' => ['Requested by Student', 'The student has requested account reactivation. The request is recorded, and the account is reactivated.'],
        'error'                => ['Deactivated in Error', 'The account was deactivated by mistake. The account is reactivated, and access is restored.'],
        'other'                => ['Other', ''],
    ];

    /** The reactivation reason that fits each original deactivation reason (listed first). */
    public const PRIORITY = [
        'leave_of_absence'        => 'returned_loa',
        'disciplinary_suspension' => 'suspension_completed',
        'security_concern'        => 'security_verified',
        'inactive_account'        => 'reviewed_cleared',
        'withdrawn_dropped'       => 're_enrolled',
        'graduated'               => 're_enrolled',
        'transferred'             => 're_enrolled',
        'requested_by_student'    => 'requested_by_student',
    ];

    public static function deactivateLabel(?string $key): string
    {
        return self::DEACTIVATE[$key][0] ?? self::LEGACY[$key] ?? (string) $key;
    }

    public static function reactivateLabel(string $key): string
    {
        return self::REACTIVATE[$key][0] ?? $key;
    }
}