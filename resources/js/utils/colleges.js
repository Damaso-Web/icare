// Finds the official college (Management > Colleges) that an account's
// college refers to. Older accounts were saved with other spellings, e.g.
// "College of Nursing" for "College of Nursing (CN)", so an exact match alone
// left a Dean with no departments to choose from.
//
//   matchCollege('College of Nursing', ['College of Nursing (CN)', ...]) -> 'College of Nursing (CN)'
//
// Returns '' when nothing matches.
export function matchCollege(name, officialNames) {
  const raw = String(name ?? '').trim();
  if (!raw || !Array.isArray(officialNames)) return '';
  const lower = raw.toLowerCase();
  const bare = s => s.replace(/\s*\([^)]*\)\s*$/, '').trim().toLowerCase();

  const exact = officialNames.find(c => c.toLowerCase() === lower);
  if (exact) return exact;

  // Same short code: "(CN)" in either name, or the name itself is the code.
  const code = (raw.match(/\(([A-Za-z]{2,8})\)\s*$/) || [])[1] || (/^[A-Za-z]{2,8}$/.test(raw) ? raw : '');
  if (code) {
    const byCode = officialNames.find(c => c.toUpperCase().endsWith(`(${code.toUpperCase()})`));
    if (byCode) return byCode;
  }

  // Same name without the code.
  return officialNames.find(c => bare(c) === bare(raw)) || '';
}
