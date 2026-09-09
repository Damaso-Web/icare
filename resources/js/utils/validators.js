export function onlyDigits(value, maxLen = null) {
  let v = value.replace(/[^0-9]/g, '');
  if (maxLen) v = v.slice(0, maxLen);
  return v;
}

export function onlyLetters(value) {
  return value.replace(/[^a-zA-Z\s\-]/g, '');
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
  return str.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
}