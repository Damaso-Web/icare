export function onlyDigits(value, maxLen = null) {
  let v = value.replace(/[^0-9]/g, '');
  if (maxLen) v = v.slice(0, maxLen);
  return v;
}

export function onlyLetters(value) {
  return value.replace(/[^a-zA-Z\s\-]/g, '');
}

export function onlyLettersStrict(value) {
  // for suffix - letters only, no spaces/hyphens
  return value.replace(/[^a-zA-Z]/g, '');
}

export function isValidEmail(email) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

export function contactNumberInput(value) {
  return onlyDigits(value, 11);
}