export function onlyDigits(value, maxLen = null) {
  let v = value.replace(/[^0-9]/g, '');
  if (maxLen) v = v.slice(0, maxLen);
  return v;
}

// Names: letters (including ñ and accented ones), spaces, hyphens and
// apostrophes - "Niño", "O'Connor", "Dela-Cruz".
export function onlyLetters(value) {
  return String(value ?? '').replace(/[^a-zA-Z\u00C0-\u024F'\s\-]/g, '');
}

// "o'connor" -> "O'Connor", "niño" -> "Niño": the first letter of each part is
// capitalised, where a part starts at the beginning, after a space, a hyphen
// or an apostrophe.
export function titleCaseName(str) {
  return String(str ?? '').toLowerCase().replace(/(^|[\s\-'])(\p{L})/gu, (m, sep, letter) => sep + letter.toUpperCase());
}

export function onlyLettersStrict(value) {
  return value.replace(/[^a-zA-Z]/g, '');
}

export function isValidEmail(email) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

export function contactNumberInput(value) {
  let v = value.replace(/[^0-9+]/g, '');
  
  // Convert +63 prefix to 0
  if (v.startsWith('+63')) {
    v = '0' + v.slice(3);
  } else if (v.startsWith('63') && v.length > 10) {
    // Handle pasted "639..." without the plus sign
    v = '0' + v.slice(2);
  }
  
  // Strip any remaining non-digit characters (like leftover +)
  v = v.replace(/[^0-9]/g, '');
  
  // Cap at 11 digits
  v = v.slice(0, 11);
  
  return v;
}

export function isValidPHContact(value) {
  return /^09\d{9}$/.test(value);
}

export function safeSearchInput(value) {
  return value.replace(/[^a-zA-Z0-9\s]/g, '');
}

export function blockSpecialKeypress(e) {
  const allowed = /^[a-zA-Z0-9\s]$/;
  if (!allowed.test(e.key)) {
    e.preventDefault();
  }
}

export function toTitleCase(str) {
  if (!str) return '';
  return str.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()).replace(/\b(Sdu|Gcu|Tmdu|Oss|Sif|Par)\b/g, m => m.toUpperCase());
}

// YYYY-MM-DD in the device's own time zone. toISOString() gives the UTC date,
// which is still "yesterday" until 8:00 AM in the Philippines.
export function localDateStr(date = new Date()) {
  const pad = n => String(n).padStart(2, '0');
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

// 24h "HH:MM(:SS)" from the database -> "h:mm AM/PM"
export function formatTime12(t) {
  if (!t) return '';
  const [h, m] = String(t).split(':');
  const hh = Number(h);
  if (Number.isNaN(hh)) return t;
  return `${((hh + 11) % 12) + 1}:${(m || '00').slice(0, 2)} ${hh >= 12 ? 'PM' : 'AM'}`;
}

// "Mon, Oct 26, 2026"
export function formatDateShort(date) {
  return date ? new Date(date).toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' }) : '-';
}

// Notes that staff type in (session notes, follow-up notes, previous
// interventions, parent conference slips): letters, numbers, spaces, line
// breaks and sentence punctuation only. Anything else is dropped as it is
// typed. app/Support/PlainText.php is the same rule on the server.
export function plainText(value) {
  return String(value ?? '').replace(/[^a-zA-Z0-9\u00C0-\u024F\s.,'\-()&:;?!/]/g, '');
}

// The referral source that goes with an account's role - the office it files
// from. Mirrors ReferralController::sourceForRole(); the label is what the
// Refer a Student form shows.
const SOURCE_BY_ROLE = {
  admin:          { value: 'admin',          label: 'GCU Office (Admin / GCU Head)' },
  gcu_staff:      { value: 'gcu_staff',      label: 'GCU Staff' },
  sdu_head:       { value: 'sdu',            label: 'Student Discipline Unit (SDU)' },
  tmdu_head:      { value: 'tmdu',           label: 'Testing and Measurement Development Unit (TMDU)' },
  tmdu_staff:     { value: 'tmdu',           label: 'Testing and Measurement Development Unit (TMDU)' },
  faculty:        { value: 'faculty',        label: 'Faculty' },
  dept_chair:     { value: 'faculty',        label: 'Faculty' },
  dean:           { value: 'dean',           label: "Dean's Office" },
  dean_secretary: { value: 'dean',           label: "Dean's Office" },
};
export function referralSourceFor(role) {
  return SOURCE_BY_ROLE[role] || { value: role || 'faculty', label: toTitleCase(role) || 'Faculty' };
}