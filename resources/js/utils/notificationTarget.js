// Where a notification should take the user: the referral, appointment or
// testing record it is about. Returns a router location or null.

export function staffNotificationTarget(data = {}) {
  if (data.referral_id)       return { name: 'referral-show', params: { id: data.referral_id } };
  if (data.testing_record_id) return { name: 'testing-show', params: { id: data.testing_record_id } };
  if (data.appointment_id)    return { name: 'appointments' };
  if (data.case_id)           return { name: 'cases' };
  return null;
}

export function studentNotificationTarget(data = {}) {
  if (data.type === 'fill_out_forms') return { name: 'student-account' };
  if (data.referral_id)       return { name: 'student-referral-show', params: { id: data.referral_id } };
  if (data.appointment_id)    return { name: 'student-appointment-show', params: { id: data.appointment_id } };
  if (data.testing_record_id) return { name: 'student-testing' };
  return null;
}