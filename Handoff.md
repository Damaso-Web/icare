# Handoff — iCARE Test Failure Verification

**Date:** 2026-11-02
**Author:** (session log)
**Branch:** `main`
**Last committed HEAD:** `8c71d06` — "fix: gracefully handle expired or invalid scheduling tokens"
**Remote:** https://github.com/Damaso-Web/icare.git

---

## Executive summary

Of the **5 failures** named in `HANDOFF_CLAUDE_TEST_FAILURES.md`:

- **3 PHPUnit failures** — all fixes verified present in source. **No source change needed.**
- **2 Cypress failures** — fix patches prepared but **NOT applied**. The `cypress/e2e/*.cy.js` files are not present in the working tree that authored this handoff. **The Cypress file owner must apply them.**

Additional items completed or verified:

- **SDU Reports tab rename** — applied to `resources/js/views/reports/Index.vue` in `/c/laragon/icare-here` working tree. **Uncommitted at time of handoff.**
- **Coverage discrepancy resolved** — the `97.86%` figure in `# PHPUnit Feature Integration Test Data.txt` is a stale intermediate snapshot. The `100%` figure in `Test-Summary.md` (32-shard merge, 8,978/8,978 lines) is authoritative and supersedes it.
- **No full-suite pass is claimed.** Neither the complete PHPUnit Feature suite nor the complete Cypress suite was rerun end-to-end in this session. Verification was by source inspection plus historical run records.

---

## The 5 failures — final status

### Fix #1 — `system_admin` returned 200 on `GET /api/users`

| Field | Value |
|---|---|
| Failing test | `tests/Feature/Rbac/RoleFeatureAccessMatrixTest.php:52` — `test_only_designated_leadership_roles_can_manage_users` |
| Original error | `Unexpected access result for role [system_admin] on [get /api/users]. Failed asserting that 200 is identical to 403.` |
| Fix | Remove `system_admin` from User Management route group in `routes/api.php` |
| Status | ✅ **Already applied at HEAD `8c71d06`** |
| Verify | `grep -n "role:admin,tmdu_head,dean,dean_secretary" routes/api.php` — must NOT contain `system_admin` |

### Fix #2 — `system_admin` returned 201 on `POST /api/users`

| Field | Value |
|---|---|
| Failing test | `tests/Feature/Rbac/RoleMatrixTest.php:81` — `test_system_admin_has_no_user_management` |
| Original error | `Expected response status code [403] but received 201.` |
| Fix | Same route group as Fix #1 |
| Status | ✅ **Already applied at HEAD `8c71d06`** |

### Fix #3 — Missing "Email Address is already used by a staff account" reason

| Field | Value |
|---|---|
| Failing test | `tests/Feature/StudentControllerRemainingCoverageTest.php:98` — `test_import_preview_reports_missing_and_invalid_values_and_repeated_emails` |
| Original error | `Failed asserting that an array contains 'Email Address is already used by a staff account'.` |
| Fix | `AccountEmail::forget();` after fixture creation, before `postJson(...)` |
| Status | ✅ **Already present** in the working tree copy of the file (line ~85 of the test method) |

### Fix #4 — Cypress `.appt-row` selector 4 s timeout

| Field | Value |
|---|---|
| Failing test | `cypress/e2e/student-appointment.cy.js:317` (in `test_checks_availability_and_submits_an_appointment_request_from_the_student_portal`) |
| Original error | `AssertionError: Timed out retrying after 4000ms: Expected to find content: 'APT-2026-0018' within the selector: '.appt-row' but never did.` |
| Fix | Extend retry window from 4 s to 10 s |
| Status | ❌ **NOT applied** — the actual `.cy.js` file is not in this working tree |

**Patch to apply:**

File: `cypress/e2e/student-appointment.cy.js`
Find (around line 316, in the block right after `cy.wait('@filterPendingAppointments')`):

    cy.contains('.appt-row', appointmentCode).should('be.visible');

Replace with:

    cy.contains('.appt-row', appointmentCode).should('be.visible', { timeout: 10000 });

**Note:** there are 7 similar `.appt-row` lines in the file. Only the one ending in `should('be.visible');` (with no `.contains('button', ...)` or `.click()` chained after it) is the target. The others are legitimate click-chains and must NOT be changed.

### Fix #5 — Cypress `cy.screenshot()` 30 s timeout

| Field | Value |
|---|---|
| Failing test | `cypress/e2e/student-profile.cy.js:108` (in `test_updates_authorized_personal_information_after_reviewing_the_privacy_confirmation`) |
| Original error | `CypressError: cy.screenshot() timed out waiting 30000ms to complete.` |
| Fix | Add explicit `timeout: 60000` and `capture: 'viewport'` |
| Status | ❌ **NOT applied** — same reason as #4 |

**Patch to apply:**

File: `cypress/e2e/student-profile.cy.js`
Find (near the end of the second `it(...)` block):

    cy.screenshot('system-test-student-profile-updated');

Replace with:

    cy.screenshot('system-test-student-profile-updated', {
        capture: 'viewport',
        timeout: 60000,
    });

Matches the pattern already used in `student-appointment.cy.js` for `system-test-staff-appointment-completed`.

---

## SDU Reports tab rename

| Field | Value |
|---|---|
| File | `resources/js/views/reports/Index.vue` |
| Change | SDU Head sees "Referrals to GCU" instead of "Referrals Reports" for the SDU referrals tab |
| Status | ⚠️ **Modified in `/c/laragon/icare-here` working tree — NOT committed, NOT pushed** |

**Patch (in `<script setup>`):**

    // SDU has only two reports: its year-end (complaints) and the students it referred.
    // The SDU Head sees the second as "Referrals to GCU"; everyone else keeps "Referrals Reports".
    const reportTabs = computed(() => {
      const types = unit.value === 'SDU'
        ? REPORT_TYPES.filter(t => ['year_end', 'referrals'].includes(t.value))
        : REPORT_TYPES;
      if (auth.user?.role !== 'sdu_head') return types;
      return types.map(t => t.value === 'referrals' ? { ...t, label: 'Referrals to GCU' } : t);
    });

**Verify visually:** log in as `sdu_head` → tab reads "Referrals to GCU". Log in as `admin` → switch to SDU report → tab still reads "Referrals Reports".

**To commit:**

    git add resources/js/views/reports/Index.vue
    git commit -m "feat(reports): SDU Head sees 'Referrals to GCU' tab label"
    git push origin main

---

## Other fixes verified (from focused-run records)

From `# PHPUnit Feature Integration Test Data.txt` and `# Integration Testing — Detailed Test Data.txt`, the following additional fixes from the batch runs were confirmed present in source files reviewed in this session:

| # | File | Change |
|---|---|---|
| 4 | `tests/Feature/RemainingCoveragePathsTest.php:87` | TrustHosts regex assertion updated to match Laravel's `^(.+\\.)?icare\\.example\\.test$` |
| 5 | `tests/Feature/RemainingNotificationPayloadTest.php` | Added `'referred_by_user_id' => $staff->id` fixture to `TestingRecord::create` |
| 6 | `tests/Feature/RemainingNotificationPayloadTest.php:78` | PAR assertion updated to `'PAR) results are ready'` (matching actual punctuation) |
| F1 | `tests/Feature/Final190CoverageTest.php` | Test-local unique index `test_user_import_employee_id_unique` + scoped query listener for the employee_id race |
| F2 | `tests/Feature/Final190CoverageTest.php` | `addGlobalScope('coverage-string-date-cast', ...withCasts(['appointment_date' => 'string']))` in try/finally for the month-availability test |

---

## Coverage discrepancy — explained

Two contradictory coverage figures exist in the record:

- **`97.86%`** (8,669 / 8,859 executable lines) — from `# PHPUnit Feature Integration Test Data.txt`; explicitly labelled **"100% coverage target: not met"**
- **`100.00%`** (8,978 / 8,978 executable lines) — from `Test-Summary.md` and `# Integration Testing — Detailed Test Data.txt`

**Resolution:** The 97.86% figure is an **intermediate merge** of 13 shards (701 test IDs) that predates the `Final190CoverageTest` shard. The 100% figure is the final **32-shard merge** (760 test IDs) that includes all test files. The 97.86% snapshot is stale.

**Recommendation:** Add a header line to `# PHPUnit Feature Integration Test Data.txt`:

> *Intermediate snapshot — superseded by the final 32-shard merge in Test-Summary.md §1.*

---

## Historical run results (preserved, not superseded)

- **PHPUnit Feature batch (2026-11-02):** 653 tests / 3,283 assertions / **3 failures** across 107 files in 11 batches
- **PHPUnit focused correction rerun:** 65 tests / 303 assertions / **0 failures**
- **Cypress full suite (2026-11-01 14:04):** 49 tests / **47 passed, 2 failed**, 0 skipped; **95.92%** pass rate; Cypress exit code 2; duration 15m 05s
- **Cypress targeted third run (user-reported):** 2/2 passed on both previously failing cases (no raw output or exit code provided)

These remain the last recorded complete-suite runs. No new complete suite has been executed.

---

## What remains — action items

### 1. Apply Cypress fixes #4 and #5

See the exact patches above. Two files, two edits.

### 2. Delete duplicate test file

- **Delete:** `tests/Feature/ReminderCommandsTest(1).php`
- **Keep:** `tests/Feature/ReminderCommandsTest.php`
- **Reason:** Both declare `class ReminderCommandsTest` — PHPUnit will fatal on load with "Cannot declare class because the name is already in use"

    rm "tests/Feature/ReminderCommandsTest(1).php"

### 3. Commit and push the SDU tab rename

If permanent, follow the commands in the "SDU Reports tab rename" section.

### 4. Run the complete Feature suite (recommended)

    php artisan test tests/Feature 2>&1 | tee feature-run.log
    tail -60 feature-run.log

Record: test count, pass/fail, exit code. This is the only way to confirm no regressions from the various fixes.

### 5. (Optional) Rerun the two Cypress specs with the fixes applied

    npx cypress run --spec "cypress/e2e/student-appointment.cy.js,cypress/e2e/student-profile.cy.js"

Expected: both previously failing tests pass.

---

## Files reviewed in this session

- `routes/api.php` — User Management route group verified (fixes #1, #2)
- `tests/Feature/Rbac/RoleFeatureAccessMatrixTest.php` — failing test source reviewed
- `tests/Feature/Rbac/RoleMatrixTest.php` — failing test source reviewed
- `tests/Feature/StudentControllerRemainingCoverageTest.php` — `AccountEmail::forget()` confirmed present (fix #3)
- `tests/Feature/RemainingCoveragePathsTest.php` — TrustHosts fix confirmed
- `tests/Feature/RemainingNotificationPayloadTest.php` — fixture + PAR assertion fix confirmed
- `tests/Feature/Final190CoverageTest.php` — race index + withCasts fixes confirmed
- `resources/js/views/reports/Index.vue` — SDU rename applied in working tree
- `cypress/e2e/student-appointment.cy.js` — transcript only, fix #4 patch prepared but not applied
- `cypress/e2e/student-profile.cy.js` — transcript only, fix #5 patch prepared but not applied
- ~95 additional Feature test files reviewed for reference and consistency

## What is NOT verified in this session

- **Complete Feature suite not rerun in current working tree.** The last recorded complete run is the historical 653-test batch.
- **Complete Cypress suite not rerun.** Fixes #4 and #5 are timeouts (mitigations), not root-cause fixes — they reduce flake but do not prove the underlying test issue is gone.
- **14:04 Cypress raw log not available.** The diagnostic summary records the failure correctly, but the raw output file was not provided to this session.
- **100% coverage figure not independently re-generated.** Relies on the merge records in `Test-Summary.md`.

## No pass claims

This handoff does **not** claim:

- "Complete PHPUnit Feature suite passes" — only "3 PHPUnit fixes verified present; historical batch retained"
- "Complete Cypress suite passes" — only "2 targeted retries reportedly passed; complete suite not rerun"

Any such claim must be backed by an actual full-suite run log with totals and exit code.

---

**End of handoff.**
