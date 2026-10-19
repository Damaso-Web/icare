// Coming back to a sign-in page from "Forgot password" / "Reset password"
// should land on the sign-in form, not on the privacy notice again (the notice
// was already accepted just before). The flag is used once.
export function skipConsentNextTime(type) {
  try { sessionStorage.setItem('consent_skip_' + type, '1'); } catch (e) { /* shown again */ }
}
export function consumeConsentSkip(type) {
  try {
    const on = sessionStorage.getItem('consent_skip_' + type) === '1';
    sessionStorage.removeItem('consent_skip_' + type);
    return on;
  } catch (e) { return false; }
}

// Keeping the agreement ticked while the person moves around within the same
// browser tab (e.g. from the sign-in form back to the agreement). It is kept
// in sessionStorage, so closing the tab or browser forgets it. It is also
// cleared on logout, when the sign-in type is switched, and whenever the text
// of the agreement / notice changes (the saved hash of the text no longer
// matches).
const OK_KEY = 'consent_ok_';

export function hashText(text) {
  let h = 5381;
  const t = String(text || '').replace(/\s+/g, ' ').trim();
  for (let i = 0; i < t.length; i++) h = ((h << 5) + h + t.charCodeAt(i)) | 0;
  return String(h);
}
export function rememberConsent(type, hash) {
  try { sessionStorage.setItem(OK_KEY + type, hash); } catch (e) { /* asked again */ }
}
export function consentStillValid(type, hash) {
  try { return sessionStorage.getItem(OK_KEY + type) === hash; } catch (e) { return false; }
}
/** Forget the agreement for one type ('staff' / 'student'), or both. */
export function clearConsent(type) {
  try {
    (type ? [type] : ['staff', 'student']).forEach(t => sessionStorage.removeItem(OK_KEY + t));
  } catch (e) { /* nothing stored */ }
}