<?php

namespace App\Support;

/**
 * Reasons an admin must pick when deactivating or reactivating an employee
 * account (User Management). Keys are what the API accepts; the labels and
 * descriptions are shown to the admin and written to the Audit Trail.
 */
class EmployeeAccountReasons
{
    public const DEACTIVATE = [
        'resigned'          => ['Resigned', 'The employee has resigned and no longer needs access to the system. The account is deactivated, and records are retained.'],
        'retired'           => ['Retired', 'The employee has retired from the university. The account is deactivated, and records are retained for reference.'],
        'end_of_contract'   => ['End of Contract / Term', "The employee's contract or term has ended. The account is deactivated and can be reactivated if the employee is rehired."],
        'transferred'       => ['Transferred to Another Office or Campus', 'The employee has been reassigned outside this office or unit. The account is deactivated, and a new account can be created if needed.'],
        'loa'               => ['Leave of Absence (LOA)', 'The employee is on an approved leave. The account is temporarily deactivated and can be reactivated upon return.'],
        'administrative'    => ['Administrative Action', "The employee's access is deactivated pending or following an official administrative decision. The account can be reactivated once the action is lifted or completed."],
        'security'          => ['Security Concern', "The account is deactivated because of suspected unauthorized access or unusual activity. It remains deactivated until the employee's identity and account security are verified."],
        'inactive'          => ['Inactive Account', 'The account has had no activity for an extended period. It is deactivated for security purposes and can be reactivated upon request.'],
        'duplicate'         => ['Duplicate Account / Created in Error', 'The account was created in error or duplicates another account. It should not be used.'],
        'other'             => ['Other', ''],
    ];

    public const REACTIVATE = [
        'returned_loa'      => ['Returned from Leave of Absence (LOA)', 'The employee has returned from an approved leave. The account is reactivated.'],
        'rehired'           => ['Rehired / Contract Renewed', 'The employee has been rehired or their contract has been renewed. The account is reactivated, and previous records are retained.'],
        'action_lifted'     => ['Administrative Action Lifted', 'The administrative action has ended or been lifted. The account is reactivated.'],
        'security_verified' => ['Security Verified', "The employee's identity and account security have been verified. The account is reactivated, and a password reset is required at the next login."],
        'error'             => ['Deactivated in Error', 'The account was deactivated by mistake. The account is reactivated, and access is restored.'],
        'other'             => ['Other', ''],
    ];

    /** Which reactivation reason to list first for each original deactivation reason. */
    public const PRIORITY = [
        'loa'            => 'returned_loa',
        'end_of_contract'=> 'rehired',
        'resigned'       => 'rehired',
        'retired'        => 'rehired',
        'transferred'    => 'rehired',
        'administrative' => 'action_lifted',
        'security'       => 'security_verified',
        'inactive'       => 'security_verified',
    ];

    // B591: which reactivation reasons make sense for an account deactivated
    // under a given label - e.g. an account deactivated for "End of Contract
    // / Term" can only be reactivated as "Rehired / Contract Renewed" (or the
    // two escape hatches below), never "Security Verified". Keyed by the
    // deactivation LABEL because that's what's stored on the user record
    // (deactivation_reason). resources/js/constants/accountReasons.js's
    // reactivationReasonsFor() is the same rule on the frontend.
    public const RELATED = [
        'Leave of Absence (LOA)'                   => ['returned_loa'],
        'End of Contract / Term'                    => ['rehired'],
        'Resigned'                                   => ['rehired'],
        'Retired'                                     => ['rehired'],
        'Transferred to Another Office or Campus'    => ['rehired'],
        'Administrative Action'                       => ['action_lifted'],
        'Security Concern'                            => ['security_verified'],
        'Inactive Account'                            => ['security_verified'],
        'Duplicate Account / Created in Error'        => [],
    ];

    /**
     * Reactivation reason keys allowed for an account deactivated under
     * $deactivationLabel. "Deactivated in Error" and "Other" are always
     * allowed as an escape hatch; an unrecognized or "Other" original
     * reason can't be narrowed, so every reactivation reason stays open.
     */
    public static function allowedReactivationKeys(?string $deactivationLabel): array
    {
        if ($deactivationLabel === null || !array_key_exists($deactivationLabel, self::RELATED)) {
            return array_keys(self::REACTIVATE);
        }
        return array_merge(self::RELATED[$deactivationLabel], ['error', 'other']);
    }

    public static function label(string $kind, string $key): string
    {
        $list = $kind === 'reactivate' ? self::REACTIVATE : self::DEACTIVATE;
        return $list[$key][0] ?? $key;
    }

    public static function description(string $kind, string $key): string
    {
        $list = $kind === 'reactivate' ? self::REACTIVATE : self::DEACTIVATE;
        return $list[$key][1] ?? '';
    }
}