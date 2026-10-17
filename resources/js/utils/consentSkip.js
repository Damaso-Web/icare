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