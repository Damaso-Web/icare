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
  return onlyDigits(value, 11);
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