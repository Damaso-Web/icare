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

const OK_KEY = 'consent_ok_';
const CONSENT_TTL_MS = 7 * 24 * 60 * 60 * 1000; // 7 days

export function hashText(text) {
  let h = 5381;
  const t = String(text || '').replace(/\s+/g, ' ').trim();
  for (let i = 0; i < t.length; i++) h = ((h << 5) + h + t.charCodeAt(i)) | 0;
  return String(h);
}
export function rememberConsent(type, hash) {
  try {
    localStorage.setItem(OK_KEY + type, JSON.stringify({ hash, acceptedAt: Date.now() }));
  } catch (e) { /* asked again */ }
}
export function consentStillValid(type, hash) {
  try {
    const raw = localStorage.getItem(OK_KEY + type);
    if (!raw) return false;
    // Older saves (before the 7-day timer) stored a bare hash string, not
    // JSON - treat those as expired so everyone lands on the new scheme.
    let saved;
    try { saved = JSON.parse(raw); } catch (e) { return false; }
    if (!saved || typeof saved !== 'object') return false;
    if (saved.hash !== hash) return false;
    return (Date.now() - Number(saved.acceptedAt || 0)) < CONSENT_TTL_MS;
  } catch (e) { return false; }
}
/** Forget the agreement for one type ('staff' / 'student'), or both. */
export function clearConsent(type) {
  try {
    (type ? [type] : ['staff', 'student']).forEach(t => localStorage.removeItem(OK_KEY + t));
  } catch (e) { /* nothing stored */ }
}