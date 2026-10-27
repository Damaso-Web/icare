import { describe, it, expect } from 'vitest';
import {
  onlyDigits,
  onlyLetters,
  titleCaseName,
  capitalizeFirst,
  isValidEmail,
  contactNumberInput,
  isValidPHContact,
  toTitleCase,
  formatTime12,
  plainText,
  hasLetter,
  referralSourceFor,
} from '../utils/validators.js';

// B113/manuscript follow-up: these cover the input-sanitizing and
// validation helpers shared by referral, complaint, and profile forms
// across the app, so a regression here would silently let bad data
// (names with digits, malformed PH numbers, digits-only "answers") reach
// the backend. They're pure functions, so no mocking is needed.

describe('onlyDigits', () => {
  it('strips non-digit characters', () => {
    expect(onlyDigits('abc123-456')).toBe('123456');
  });

  it('respects an optional max length', () => {
    expect(onlyDigits('12345678901', 5)).toBe('12345');
  });
});

describe('onlyLetters', () => {
  it('keeps accented letters, spaces, hyphens and apostrophes', () => {
    expect(onlyLetters("Niño O'Connor-Dela Cruz 3rd")).toBe("Niño O'Connor-Dela Cruz rd");
  });
});

describe('titleCaseName', () => {
  it('capitalizes after spaces, hyphens and apostrophes', () => {
    expect(titleCaseName("o'connor")).toBe("O'Connor");
    expect(titleCaseName('niño')).toBe('Niño');
    expect(titleCaseName('dela-cruz')).toBe('Dela-Cruz');
  });
});

describe('capitalizeFirst', () => {
  it('only capitalizes the first character, leaving the rest untouched', () => {
    expect(capitalizeFirst('dela Cruz')).toBe('Dela Cruz');
    expect(capitalizeFirst('anne-marie')).toBe('Anne-marie');
    expect(capitalizeFirst('')).toBe('');
  });
});

describe('isValidEmail', () => {
  it('accepts a plausible address and rejects an incomplete one', () => {
    expect(isValidEmail('student@gmail.com')).toBe(true);
    expect(isValidEmail('not-an-email')).toBe(false);
    expect(isValidEmail('missing@domain')).toBe(false);
  });
});

describe('contactNumberInput + isValidPHContact', () => {
  it('normalizes a +63-prefixed number to the local 09 form', () => {
    expect(contactNumberInput('+639171234567')).toBe('09171234567');
  });

  it('normalizes a pasted 63-prefixed number without a plus sign', () => {
    expect(contactNumberInput('639171234567')).toBe('09171234567');
  });

  it('rejects a number that does not start with 09 as it is typed', () => {
    // '0' alone is still a valid prefix-in-progress, so it's kept; the
    // second digit not being '9' is what gets truncated.
    expect(contactNumberInput('08171234567')).toBe('0');
    // A leading digit that isn't '0' at all is rejected outright.
    expect(contactNumberInput('9171234567')).toBe('');
  });

  it('caps the result at 11 digits', () => {
    expect(contactNumberInput('091712345678888')).toBe('09171234567');
  });

  it('validates the final 09xxxxxxxxx shape', () => {
    expect(isValidPHContact('09171234567')).toBe(true);
    expect(isValidPHContact('08171234567')).toBe(false);
    expect(isValidPHContact('0917123456')).toBe(false); // too short
  });
});

describe('toTitleCase', () => {
  it('title-cases underscored values and keeps unit acronyms upper-case', () => {
    expect(toTitleCase('gcu_staff')).toBe('GCU Staff');
    expect(toTitleCase('tmdu_head')).toBe('TMDU Head');
    expect(toTitleCase('dean_secretary')).toBe('Dean Secretary');
  });
});

describe('formatTime12', () => {
  it('converts 24h database time to a 12h label', () => {
    expect(formatTime12('13:05:00')).toBe('1:05 PM');
    expect(formatTime12('00:30')).toBe('12:30 AM');
    expect(formatTime12('09:00')).toBe('9:00 AM');
  });

  it('passes through an empty value unchanged', () => {
    expect(formatTime12('')).toBe('');
  });
});

describe('plainText + hasLetter', () => {
  it('strips characters outside the allowed note character set', () => {
    expect(plainText('Reason: <script>alert(1)</script>')).toBe('Reason: scriptalert(1)/script');
  });

  it('flags a digits-only "answer" as not containing a real letter', () => {
    expect(hasLetter('12345')).toBe(false);
    expect(hasLetter('Seen in class')).toBe(true);
  });
});

describe('referralSourceFor', () => {
  it('maps a role to its referral source office', () => {
    expect(referralSourceFor('sdu_head').value).toBe('sdu');
    expect(referralSourceFor('dept_chair').value).toBe('faculty');
    expect(referralSourceFor('tmdu_staff').label).toContain('Testing and Measurement');
  });

  it('falls back to a generic label for an unmapped role', () => {
    expect(referralSourceFor('unknown_role').value).toBe('unknown_role');
  });
});