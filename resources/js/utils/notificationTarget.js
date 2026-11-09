// Where a notification should take the user: the referral, appointment or
// testing record it is about. Returns a router location or null.

// Panel fix (11/9/2026): a Dean/Dean's Secretary/Dept Chair account got
// "Access Denied" clicking a no-show escalation, unscheduled call slip, or
// unreachable-student notification - those carry appointment_id/case_id,
// which used to always route to 'appointments'/'cases' (GCU-staff-only
// pages, see router/index.js GCU_ROLES). Those accounts need the role-aware
// destination they actually have, computed here instead of in every caller.
export function staffNotificationTarget(data = {}, role = null) {
  const isDeanFamily = ['dean', 'dean_secretary', 'dept_chair'].includes(role);

  // B630: a new-device sign-in alert opens Active Sessions, where the
  // account can see and sign out the device if it wasn't them.
  if (data.type === 'new_device_login') return { name: 'my-account' };
  if (data.referral_id)       return { name: 'referral-show', params: { id: data.referral_id } };
  if (data.testing_record_id) return { name: 'testing-show', params: { id: data.testing_record_id } };
  if (data.appointment_id) {
    // No-show escalations and unscheduled call slips sent to the Dean's
    // side are both call-slip matters - 'call-slips' is the page their
    // role can actually open (router/index.js already allows it there).
    if (isDeanFamily) return { name: 'call-slips' };
    return { name: 'appointments' };
  }
  if (data.case_id) {
    // There is no Dean-side case detail page at all (GCU owns Cases
    // exclusively) - nothing to navigate to, so don't try. The
    // notification's own message already has what they need to act on.
    if (isDeanFamily) return null;
    return { name: 'cases' };
  }
  return null;
}

export function studentNotificationTarget(data = {}) {
  // B#, 11/5/2026: referred_student used to be forced to My Account along
  // with fill_out_forms - but a "you've been referred" notice is about the
  // referral, not the profile. It now falls through to the referral_id
  // check below (see ReferredStudentNotification::toArray()).
  if (data.type === 'fill_out_forms' || data.type === 'new_device_login') return { name: 'student-account' };
  if (data.referral_id)       return { name: 'student-referral-show', params: { id: data.referral_id } };
  if (data.appointment_id)    return { name: 'student-appointment-show', params: { id: data.appointment_id } };
  if (data.testing_record_id) return { name: 'student-testing' };
  return null;
}