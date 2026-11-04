import { describe, it, expect } from 'vitest';
import { emailProblem, EMAIL_EXAMPLE_MESSAGE } from '../utils/emailCheck.js';

describe('emailProblem', () => {
  it('accepts a complete, well-formed address', () => {
    expect(emailProblem('student@gmail.com')).toBe('');
  });

  it('requires a value', () => {
    expect(emailProblem('')).toBe('Email address is required.');
  });

  it('flags an incomplete domain', () => {
    expect(emailProblem('student@gmail')).toBe(EMAIL_EXAMPLE_MESSAGE);
  });

  it('catches a common typo in a known provider domain', () => {
    expect(emailProblem('student@gmial.com')).toContain('Did you mean student@gmail.com');
  });

  it('flags a domain with no dot at all as incomplete', () => {
    expect(emailProblem('student@yahoo')).toBe(EMAIL_EXAMPLE_MESSAGE);
  });

  it('flags a known provider domain with the wrong suffix', () => {
    expect(emailProblem('student@gmail.co')).toContain('looks incomplete');
  });

  // B#, 11/4/2026: staff accounts use name@bsu.edu.ph - the "bsu" provider
  // entry catches a mistyped BSU domain the same way a mistyped gmail.com
  // already was, without needing one typo-table entry per possible mistake.
  describe('the bsu.edu.ph staff domain', () => {
    it('accepts the correct domain', () => {
      expect(emailProblem('juan.delacruz@bsu.edu.ph')).toBe('');
    });

    it('flags an extra trailing letter and suggests the full domain', () => {
      expect(emailProblem('juan.delacruz@bsu.edu.phi')).toContain('Did you mean juan.delacruz@bsu.edu.ph');
    });

    it('flags a missing "u" in edu and suggests the full domain', () => {
      expect(emailProblem('juan.delacruz@bsu.ed.ph')).toContain('Did you mean juan.delacruz@bsu.edu.ph');
    });

    it('flags the wrong top-level domain and suggests bsu.edu.ph', () => {
      expect(emailProblem('juan.delacruz@bsu.com')).toContain('Did you mean juan.delacruz@bsu.edu.ph');
    });
  });
});