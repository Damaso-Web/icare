// Reasons shown in the reason dialogs. Keep the keys in sync with
// app/Support/EmployeeAccountReasons.php (employee accounts) and the Management
// delete rules in the Management*Controller classes.

const rows = (obj) => Object.entries(obj).map(([key, [label, description]]) => ({ key, label, description }));

export const EMPLOYEE_DEACTIVATE_REASONS = rows({
  resigned:        ['Resigned', 'The employee has resigned and no longer needs access to the system. The account is deactivated, and records are retained.'],
  retired:         ['Retired', 'The employee has retired from the university. The account is deactivated, and records are retained for reference.'],
  end_of_contract: ['End of Contract / Term', "The employee's contract or term has ended. The account is deactivated and can be reactivated if the employee is rehired."],
  transferred:     ['Transferred to Another Office or Campus', 'The employee has been reassigned outside this office or unit. The account is deactivated, and a new account can be created if needed.'],
  loa:             ['Leave of Absence (LOA)', 'The employee is on an approved leave. The account is temporarily deactivated and can be reactivated upon return.'],
  administrative:  ['Administrative Action', "The employee's access is deactivated pending or following an official administrative decision. The account can be reactivated once the action is lifted or completed."],
  security:        ['Security Concern', "The account is deactivated because of suspected unauthorized access or unusual activity. It remains deactivated until the employee's identity and account security are verified."],
  inactive:        ['Inactive Account', 'The account has had no activity for an extended period. It is deactivated for security purposes and can be reactivated upon request.'],
  duplicate:       ['Duplicate Account / Created in Error', 'The account was created in error or duplicates another account. It should not be used.'],
  other:           ['Other', ''],
});

export const EMPLOYEE_REACTIVATE_REASONS = rows({
  returned_loa:      ['Returned from Leave of Absence (LOA)', 'The employee has returned from an approved leave. The account is reactivated.'],
  rehired:           ['Rehired / Contract Renewed', 'The employee has been rehired or their contract has been renewed. The account is reactivated, and previous records are retained.'],
  action_lifted:     ['Administrative Action Lifted', 'The administrative action has ended or been lifted. The account is reactivated.'],
  security_verified: ['Security Verified', "The employee's identity and account security have been verified. The account is reactivated, and a password reset is required at the next login."],
  error:             ['Deactivated in Error', 'The account was deactivated by mistake. The account is reactivated, and access is restored.'],
  other:             ['Other', ''],
});

// Most relevant reactivation reason for each original deactivation reason label.
const PRIORITY = {
  'Leave of Absence (LOA)': 'returned_loa',
  'End of Contract / Term': 'rehired',
  'Resigned': 'rehired',
  'Retired': 'rehired',
  'Transferred to Another Office or Campus': 'rehired',
  'Administrative Action': 'action_lifted',
  'Security Concern': 'security_verified',
  'Inactive Account': 'security_verified',
};

/** Reactivation reasons with the one matching the original deactivation first; "Other" stays last. */
export function reactivationReasonsFor(originalLabel) {
  const first = PRIORITY[originalLabel];
  const list = [...EMPLOYEE_REACTIVATE_REASONS];
  const other = list.pop();
  list.sort((a, b) => (b.key === first) - (a.key === first));
  // "Deactivated in Error" is always offered right after the most relevant reason.
  const err = list.findIndex(r => r.key === 'error');
  if (err > 1) list.splice(1, 0, list.splice(err, 1)[0]);
  return [...list, other];
}

const created = ['created_error', 'Created in Error', 'The entry was added by mistake.'];
const other   = ['other', 'Other', ''];
const mk = (...items) => items.map(([key, label, description]) => ({ key, label, description }));

// Admin "Reset Password" reasons (keep in sync with app/Support/PasswordReset.php).
export const PASSWORD_RESET_REASONS = {
  student: mk(
    ['no_email_access', 'Student cannot access their registered email', ''],
    ['link_not_received', 'Reset link expired or was not received', ''],
    ['link_not_working', 'Reset link is not working', ''],
    ['unauthorized_access', 'Suspected unauthorized access to the account', ''],
    ['identity_verified', 'Identity verified in person at the office', ''],
    other),
  employee: mk(
    ['forgot_password', 'Employee forgot password', ''],
    ['locked_out', 'Account locked out', ''],
    ['compromised', 'Suspected compromised account', ''],
    ['new_account', 'New account setup', ''],
    other),
};

// Management > Delete: only mistaken or duplicate entries may be deleted, on every tab
// (keep in sync with ManagementGuard::reasons). Retiring an item is an Archive reason instead.
const MGMT_DELETE = mk(created, ['duplicate', 'Duplicate Entry', 'The entry duplicates an existing record.'], other);
export const MANAGEMENT_DELETE_REASONS = {
  college: MGMT_DELETE, program: MGMT_DELETE, department: MGMT_DELETE,
  wellness: MGMT_DELETE, disciplinary: MGMT_DELETE, sources: MGMT_DELETE, calendar: MGMT_DELETE, 'document header': MGMT_DELETE, 'holidays and suspensions': MGMT_DELETE,
};

// Management > Deactivate / Archive / Reactivate: reasons per tab and action
// (keep in sync with ManagementGuard::statusReasons).
const MGMT_REACTIVATE = mk(
  ['offered_again', 'Offered or Used Again', 'The item is being offered or used again.'],
  ['review_completed', 'Review or Reorganization Completed', 'The review or reorganization that paused it is finished.'],
  ['deactivated_in_error', 'Deactivated in Error', 'It was deactivated by mistake.'], other);
const sr = (deactivate, archive) => ({ inactive: mk(...deactivate, other), archived: mk(...archive, other), active: MGMT_REACTIVATE });
export const MANAGEMENT_STATUS_REASONS = {
  college: sr([['temporarily_not_offered', 'Temporarily Not Offered', ''], ['under_reorganization', 'Under Reorganization', ''], ['pending_merger', 'Pending Merger', '']],
              [['no_longer_offered', 'No Longer Offered', ''], ['merged', 'Merged', ''], ['renamed_replaced', 'Renamed or Replaced', '']]),
  program: sr([['temporarily_not_offered', 'Temporarily Not Offered', ''], ['under_curriculum_review', 'Under Curriculum Review', '']],
              [['phased_out', 'Phased Out', ''], ['replaced', 'Replaced by New Program or Curriculum', ''], ['merged', 'Merged', '']]),
  department: sr([['under_reorganization', 'Under Reorganization', ''], ['pending_merger', 'Pending Merger', '']],
                 [['merged', 'Merged', ''], ['dissolved', 'Dissolved', '']]),
  wellness: sr([['temporarily_unavailable', 'Temporarily Unavailable', ''], ['under_review', 'Under Review', '']],
               [['service_discontinued', 'Service Discontinued', ''], ['replaced_merged', 'Replaced or Merged with Another Service', '']]),
  disciplinary: sr([['temporarily_unavailable', 'Temporarily Unavailable', ''], ['pending_policy_update', 'Pending Policy Update', '']],
                   [['discontinued', 'Discontinued', ''], ['replaced_policy', 'Replaced by Updated Policy or Procedure', '']]),
  calendar: sr([['entered_ahead', 'Entered Ahead of Time', ''], ['schedule_under_review', 'Schedule Under Review', '']],
               [['term_ended', 'Term Ended', ''], ['replaced_calendar', 'Replaced by Updated Calendar', '']]),
  'holidays and suspensions': sr([], [['date_changed', 'Date Changed by the University', ''], ['working_day', 'Declared a Working Day', ''], ['replaced_announcement', 'Replaced by an Updated Announcement', ''], ['event_cancelled', 'Event Cancelled or Moved', '']]),
  'document header': sr([], [['outdated_format', 'Outdated Format', ''], ['replaced_version', 'Replaced by New Version', ''], ['office_logo_changed', 'Office Name or Logo Changed', '']]),
  sources: sr([['not_currently_used', 'Not Currently Used', ''], ['under_review', 'Under Review', '']],
              [['no_longer_used', 'No Longer Used', ''], ['merged', 'Merged with Another Source', '']]),
};

// ---- Student accounts (keep in sync with app/Support/StudentAccountReasons.php) ----
export const STUDENT_DEACTIVATE_REASONS = rows({
  withdrawn_dropped:       ['Withdrawn/Dropped', 'The student has officially withdrawn or dropped from the university and no longer needs access to the system. The account is deactivated and retained for record purposes.'],
  leave_of_absence:        ['Leave of Absence (LOA)', 'The student is on an approved leave of absence for the current term. The account is temporarily deactivated and can be reactivated when the student returns.'],
  disciplinary_suspension: ['Disciplinary Suspension', "The student's access is deactivated for the duration of an official suspension. The account can be reactivated once the suspension period ends."],
  graduated:               ['Graduated', 'The student has completed the program and no longer needs access to the system. The account is deactivated, and the records are retained for reference.'],
  transferred:             ['Transferred to Another School', "The student has transferred out of the university. The account is deactivated, and the student's case history is retained."],
  security_concern:        ['Security Concern', "The account is deactivated because of suspected unauthorized access or unusual activity. It remains deactivated until the student's identity and account security are verified."],
  inactive_account:        ['Inactive Account', 'The account has had no activity for an extended period. It is deactivated for security purposes and can be reactivated upon request.'],
  requested_by_student:    ['Requested by Student', 'The student has requested account deactivation. The request is recorded, and the account can be reactivated if the student requests it.'],
  other:                   ['Other', ''],
});

export const STUDENT_REACTIVATE_REASONS = rows({
  re_enrolled:          ['Re-enrolled', 'The student has re-enrolled in the university. The account is reactivated, and previous records are retained.'],
  returned_loa:         ['Returned from Leave of Absence (LOA)', 'The student has returned from an approved leave of absence and is enrolled for the current term. The account is reactivated.'],
  suspension_completed: ['Suspension Completed', "The student's suspension period has ended. The account is reactivated, and access is restored."],
  security_verified:    ['Security Verified', "The student's identity and account security have been verified. The account is reactivated, and a password reset is required at the next login."],
  reviewed_cleared:     ['Reviewed and Cleared', "The inactive account has been reviewed and cleared for use at the student's request. The account is reactivated."],
  requested_by_student: ['Requested by Student', 'The student has requested account reactivation. The request is recorded, and the account is reactivated.'],
  error:                ['Deactivated in Error', 'The account was deactivated by mistake. The account is reactivated, and access is restored.'],
  other:                ['Other', ''],
});

const STUDENT_PRIORITY = {
  leave_of_absence: 'returned_loa',
  disciplinary_suspension: 'suspension_completed',
  security_concern: 'security_verified',
  inactive_account: 'reviewed_cleared',
  withdrawn_dropped: 're_enrolled',
  graduated: 're_enrolled',
  transferred: 're_enrolled',
  requested_by_student: 'requested_by_student',
};

export function studentDeactivateLabel(key) {
  return STUDENT_DEACTIVATE_REASONS.find(r => r.key === key)?.label
    || (key === 'no_longer_enrolled' ? 'No Longer Enrolled' : (key || ''));
}

/**
 * Reactivation reasons for a student, from the original deactivation reason key.
 * The matching reason goes first, "Deactivated in Error" is always available
 * right after it, and "Other" stays last. Reasons that contradict how the
 * account was deactivated (e.g. "Returned from LOA" for a suspension) are left
 * out; with an unknown original reason the whole list is offered.
 */
export function studentReactivationReasonsFor(originalKey) {
  const first = STUDENT_PRIORITY[originalKey];
  const all = STUDENT_REACTIVATE_REASONS;
  const other = all.find(r => r.key === 'other');
  const error = all.find(r => r.key === 'error');
  if (!first) return all;
  // Re-enrolled only fits a student who left the university; Requested by
  // Student fits those, an inactive account and a student's own request.
  const leftSchool = ['withdrawn_dropped', 'graduated', 'transferred'].includes(originalKey);
  const wanted = [first];
  if (leftSchool) wanted.push('requested_by_student');
  if (originalKey === 'inactive_account') wanted.push('requested_by_student');
  const list = wanted.filter((k, i, a) => a.indexOf(k) === i).map(k => all.find(r => r.key === k));
  return [...list, error, other];
}