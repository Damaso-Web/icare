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

// Remembering that the agreement was already accepted on this device, so it
// isn't asked again on every visit/login - only once per device, ever, per
// sign-in type ('staff' / 'student'). Kept in localStorage (not
// sessionStorage), so it survives closing the tab/browser and logging out.
// It is still asked again automatically if the text of the agreement/notice
// itself changes later (the saved hash of the text no longer matches), since
// that's a materially different thing to agree to.
const OK_KEY = 'consent_ok_';

export function hashText(text) {
  let h = 5381;
  const t = String(text || '').replace(/\s+/g, ' ').trim();
  for (let i = 0; i < t.length; i++) h = ((h << 5) + h + t.charCodeAt(i)) | 0;
  return String(h);
}
export function rememberConsent(type, hash) {
  try { localStorage.setItem(OK_KEY + type, hash); } catch (e) { /* asked again */ }
}
export function consentStillValid(type, hash) {
  try { return localStorage.getItem(OK_KEY + type) === hash; } catch (e) { return false; }
}
/** Forget the agreement for one type ('staff' / 'student'), or both. */
export function clearConsent(type) {
  try {
    (type ? [type] : ['staff', 'student']).forEach(t => localStorage.removeItem(OK_KEY + t));
  } catch (e) { /* nothing stored */ }
}