// Finds the official college (Management > Colleges) that an account's

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
