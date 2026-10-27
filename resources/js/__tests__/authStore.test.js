import { describe, it, expect, beforeEach, vi } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';

// axios is only imported by the store for its login/logout HTTP calls,
// which these tests don't exercise - the getters under test run on
// plain state, so a minimal mock keeps the module importable under jsdom.
vi.mock('axios', () => ({
  default: {
    defaults: { headers: { common: {} } },
    post: vi.fn(),
  },
}));

import { useAuthStore } from '../stores/auth.js';

// These getters are the single source of truth the Vue frontend uses to
// decide what a signed-in user can see - e.g. the Routes / nav guards and
// page-level v-if checks all read from here. A wrong mapping here (such as
// Dept Chair quietly gaining GCU's counseling permissions) would be a real
// access-control bug, not just a cosmetic one, so these are worth testing
// directly rather than only through the backend.

function setRole(store, role) {
  store.user = { role };
  store.token = 'fake-token';
}

describe('useAuthStore role getters', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    localStorage.clear();
  });

  it('is not authenticated without a token', () => {
    const store = useAuthStore();
    expect(store.isAuthenticated).toBe(false);
  });

  it('treats TMDU head as TMDU staff, but not vice versa for head-only checks', () => {
    const store = useAuthStore();
    setRole(store, 'tmdu_staff');
    expect(store.isTMDUStaff).toBe(true);
    expect(store.isTMDUHead).toBe(false);

    setRole(store, 'tmdu_head');
    expect(store.isTMDUStaff).toBe(true);
    expect(store.isTMDUHead).toBe(true);
  });

  it('groups faculty and department chair under isFaculty', () => {
    const store = useAuthStore();
    setRole(store, 'dept_chair');
    expect(store.isFaculty).toBe(true);
    expect(store.isDeptChair).toBe(true);

    setRole(store, 'faculty');
    expect(store.isFaculty).toBe(true);
    expect(store.isDeptChair).toBe(false);
  });

  it('groups dean and dean secretary under isDean', () => {
    const store = useAuthStore();
    setRole(store, 'dean_secretary');
    expect(store.isDean).toBe(true);
  });

  it('only admin and GCU staff can counsel', () => {
    const store = useAuthStore();
    setRole(store, 'gcu_staff');
    expect(store.canCounsel).toBe(true);

    setRole(store, 'sdu_head');
    expect(store.canCounsel).toBe(false);
  });

  it('excludes faculty-facing roles from case visibility', () => {
    const store = useAuthStore();
    setRole(store, 'faculty');
    expect(store.canViewCases).toBe(false);

    setRole(store, 'gcu_staff');
    expect(store.canViewCases).toBe(true);
  });

  it('setSession persists the token and user to localStorage', () => {
    const store = useAuthStore();
    store.setSession({ token: 'abc123', user: { id: 1, role: 'gcu_staff' } });
    expect(store.token).toBe('abc123');
    expect(JSON.parse(localStorage.getItem('user')).role).toBe('gcu_staff');
  });

  it('logout clears the session even without a live backend', async () => {
    const store = useAuthStore();
    store.setSession({ token: 'abc123', user: { id: 1, role: 'gcu_staff' } });
    await store.logout();
    expect(store.token).toBeNull();
    expect(store.user).toBeNull();
    expect(localStorage.getItem('token')).toBeNull();
  });
});