// "Remember me" on the two sign-in pages.
//
// Ticked:   the email / Student ID is filled in next time (never the
//           password) and the sign-in survives closing the browser.
// Unticked: nothing is remembered and closing the browser signs the person
//           out - the right choice on a shared computer.
//
// Either way the server still ends a sign-in after its own time limit; this
// only decides what this browser keeps.
//
// A sign-in that should end with the browser is marked "session only". The
// browser's own session cookie tells us whether it has been closed since:
// that cookie is shared by every tab and dropped when the browser closes.

const ALIVE_COOKIE = 'icare_browser_session';

// kind -> where that sign-in and its remembered id are kept
const KINDS = {
  staff:   { keys: ['token', 'user'],            flag: 'staff_session_only',   remembered: 'remembered_email' },
  student: { keys: ['student_token', 'student'], flag: 'student_session_only', remembered: 'remembered_student_id' },
};

function browserStillOpen() {
  return document.cookie.split('; ').some(c => c.startsWith(`${ALIVE_COOKIE}=`));
}

function markBrowserOpen() {
  document.cookie = `${ALIVE_COOKIE}=1; path=/; SameSite=Lax`;
}

/** Run once when the app starts, before anything reads the saved sign-in. */
export function endSessionOnlySignIns() {
  try {
    if (!browserStillOpen()) {
      for (const { keys, flag } of Object.values(KINDS)) {
        if (localStorage.getItem(flag)) {
          keys.forEach(k => localStorage.removeItem(k));
          localStorage.removeItem(flag);
        }
      }
    }
    markBrowserOpen();
  } catch (e) {
    /* storage or cookies unavailable - leave the sign-in as it is */
  }
}

/** Call right after a successful sign-in. `id` is the email or Student ID. */
export function applyRememberMe(kind, remember, id) {
  const { flag, remembered } = KINDS[kind];
  try {
    if (remember) {
      localStorage.removeItem(flag);
      localStorage.setItem(remembered, id);
    } else {
      localStorage.setItem(flag, '1');
      localStorage.removeItem(remembered);
    }
    markBrowserOpen();
  } catch (e) {
    /* nothing to remember with */
  }
}

/** The email / Student ID remembered on this browser, or ''. */
export function rememberedId(kind) {
  try {
    return localStorage.getItem(KINDS[kind].remembered) || '';
  } catch (e) {
    return '';
  }
}
