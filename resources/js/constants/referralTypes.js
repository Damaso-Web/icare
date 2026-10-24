// B551: the full set of referral_type values the system can produce, kept in
// sync with App\Models\Referral (sameUnitTypes() / appointmentTypeFor() /
// requiredDocumentsFor()) and with the Referral Type options offered when a
// referral is created. Anywhere a "cover every type" list is needed - such as
// the My Referrals "All Types" filter - should build its options from this,
// not from whatever types happen to already be present in some other list of
// records, so the filter doesn't silently fall behind as types are used.
export const REFERRAL_TYPES = [
  'counseling',
  'disciplinary',
  'psychological_testing',
  'class_attendance',
  'academic_deficiency',
  'leave_of_absence',
  'withdrawal',
  'readmission',
  'shifting',
];