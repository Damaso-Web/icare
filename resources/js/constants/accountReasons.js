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

// Management > Delete: reasons per tab (see ManagementDeletion.php).
export const MANAGEMENT_DELETE_REASONS = {
  college: mk(created, ['duplicate', 'Duplicate Entry', 'The entry duplicates an existing record.'],
    ['no_longer_offered', 'No Longer Offered', 'The college has been dissolved or closed.'],
    ['merged', 'Merged with Another College', 'Its records were moved to another college.'], other),
  program: mk(created, ['duplicate', 'Duplicate Entry', 'The entry duplicates an existing record.'],
    ['discontinued', 'Program Discontinued', 'The program is no longer offered.'],
    ['replaced', 'Renamed or Replaced', 'The program was replaced by a new one, and its records were moved.'], other),
  department: mk(created, ['duplicate', 'Duplicate Entry', 'The entry duplicates an existing record.'],
    ['dissolved', 'Department Dissolved', 'The department no longer exists.'],
    ['merged', 'Merged with Another Department', 'Its records were moved to another department.'], other),
  wellness: mk(created, ['duplicate', 'Duplicate Entry', 'The entry duplicates an existing record.'],
    ['no_longer_offered', 'Service No Longer Offered', 'The service has been discontinued.'],
    ['replaced', 'Replaced by Another Service', 'The service was renamed or replaced.'], other),
  disciplinary: mk(created, ['duplicate', 'Duplicate Entry', 'The entry duplicates an existing record.'],
    ['no_longer_offered', 'Service No Longer Offered', 'The service has been discontinued.'],
    ['policy_change', 'Replaced Following a Policy Change', 'The service was replaced due to an updated policy.'], other),
  sources: mk(created, ['duplicate', 'Duplicate Entry', 'The entry duplicates an existing record.'],
    ['no_longer_used', 'No Longer Used', 'This option is no longer used.'], other),
  calendar: mk(created, ['duplicate', 'Duplicate Entry', 'The entry duplicates an existing record.'],
    ['no_longer_used', 'No Longer Used', 'This option is no longer used.'], other),
};