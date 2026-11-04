// Where a notification should take the user: the referral, appointment or
// testing record it is about. Returns a router location or null.

export function staffNotificationTarget(data = {}) {
  // B630: a new-device sign-in alert opens Active Sessions, where the
  // account can see and sign out the device if it wasn't them.
  if (data.type === 'new_device_login') return { name: 'my-account' };
  if (data.referral_id)       return { name: 'referral-show', params: { id: data.referral_id } };
  if (data.testing_record_id) return { name: 'testing-show', params: { id: data.testing_record_id } };
  if (data.appointment_id)    return { name: 'appointments' };
  if (data.case_id)           return { name: 'cases' };
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