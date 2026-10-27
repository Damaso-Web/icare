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
});