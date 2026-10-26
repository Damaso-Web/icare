// Checks that a personal email looks complete, not just "has a dot".
const PROVIDERS = {
  gmail: ['gmail.com'],
  yahoo: ['yahoo.com', 'yahoo.com.ph'],
  outlook: ['outlook.com', 'outlook.ph'],
  hotmail: ['hotmail.com'],
  icloud: ['icloud.com'],
  live: ['live.com'],
  proton: ['proton.me'],
  protonmail: ['protonmail.com'],
};
const TYPOS = { 'gmial.com': 'gmail.com', 'gmai.com': 'gmail.com', 'gamil.com': 'gmail.com', 'gmail.con': 'gmail.com', 'yaho.com': 'yahoo.com', 'yahoo.con': 'yahoo.com', 'hotmial.com': 'hotmail.com' };

export const EMAIL_EXAMPLE_MESSAGE = 'Enter a complete email address, like name@gmail.com.';

// '' when fine, otherwise the message to show.
export function emailProblem(value) {
  const v = String(value ?? '').trim().toLowerCase();
  if (!v) return 'Email address is required.';
  const m = /^([^\s@]+)@([^\s@]+)$/.exec(v);
  if (!m) return EMAIL_EXAMPLE_MESSAGE;
  const local = m[1];
  const domain = m[2];
  if (local.startsWith('.') || local.endsWith('.') || local.includes('..')) return EMAIL_EXAMPLE_MESSAGE;
  if (!/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/.test(domain)) return EMAIL_EXAMPLE_MESSAGE;
  if (!/[a-z]{2,}$/.test(domain)) return EMAIL_EXAMPLE_MESSAGE;
  if (TYPOS[domain]) return `Did you mean ${local}@${TYPOS[domain]}? ${EMAIL_EXAMPLE_MESSAGE}`;
  const first = domain.split('.')[0];
  if (PROVIDERS[first] && !PROVIDERS[first].includes(domain)) {
    return `"${domain}" looks incomplete. Did you mean ${local}@${PROVIDERS[first][0]}?`;
  }
  return '';
}