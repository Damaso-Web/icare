// System test: drives a real Chromium browser through the actual GCU staff
// sign-in flow (landing page -> Login Choice -> OSS Personnel form ->
// credentials -> the one-time Confidentiality Agreement modal) against a
// running copy of the app, and checks that an authenticated session with the
// right role actually comes out the other end.
//
// This is a *system* test, not a unit test - it talks to a real backend over
// HTTP, so it needs both servers up first:
//
//   1. A database migrated and with an E2E user seeded (see README.md in
//      this folder for the exact commands - a disposable SQLite file is the
//      simplest option, so this never touches your real dev/production DB).
//   2. `php artisan serve --port=8099` (match PLAYWRIGHT_BASE_URL below)
//   3. `npm run dev` (Vite dev server, for the Vue asset bundle)
//
// Then run: node tests/e2e/login.e2e.cjs
//
// Config is read from environment variables so this never hard-codes a
// password into git history beyond this test-only placeholder account.
const path = require('path');
const fs = require('fs');
const { chromium } = require('playwright');

const BASE_URL = process.env.PLAYWRIGHT_BASE_URL || 'http://127.0.0.1:8099';
const EMAIL = process.env.E2E_USER_EMAIL || 'e2e.gcu@icare.test';
const PASSWORD = process.env.E2E_USER_PASSWORD || 'E2ePassw0rd!';
const SHOT_DIR = path.join(__dirname, 'screenshots');

async function main() {
  fs.mkdirSync(SHOT_DIR, { recursive: true });

  const browser = await chromium.launch();
  const page = await browser.newPage();
  const pageErrors = [];
  page.on('pageerror', (err) => pageErrors.push(String(err)));

  console.log('[1/6] Landing page...');
  await page.goto(BASE_URL, { waitUntil: 'networkidle' });

  console.log('[2/6] Clicking Log in...');
  await page.locator('a:has-text("Log in"), button:has-text("Log in")').first().click();
  await page.waitForLoadState('networkidle');

  console.log('[3/6] Picking OSS Personnel sign-in...');
  await page.locator('text=OSS Personnel').first().click();
  await page.waitForLoadState('networkidle');

  console.log('[4/6] Filling in GCU staff credentials...');
  const emailInput = page.locator('input[type="email"], input[name="email"], input#email').first();
  const passwordInput = page.locator('input[type="password"]').first();
  await emailInput.waitFor({ state: 'visible', timeout: 10000 });
  await emailInput.fill(EMAIL);
  await passwordInput.fill(PASSWORD);
  await page.screenshot({ path: `${SHOT_DIR}/01_credentials_filled.png`, fullPage: true });

  console.log('[5/6] Submitting Sign In...');
  await page.locator('button:has-text("Sign In"), button[type="submit"]').first().click();
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(1000);

  // First-ever staff login shows a one-time Confidentiality Agreement modal
  // whose checkbox stays disabled until its notice (`.cg-text`) is scrolled
  // to the end - this is a deliberate UX gate, not a bug, so the test works
  // with it instead of trying to bypass it.
  const agreementHeading = page.locator('text=Personnel Confidentiality and Data Handling Agreement');
  if (await agreementHeading.count()) {
    console.log('[5b/6] Confidentiality agreement shown - scrolling and accepting...');
    await page.evaluate(() => {
      const el = document.querySelector('.cg-text');
      if (el) el.scrollTop = el.scrollHeight;
    });
    await page.waitForTimeout(600);

    const checkbox = page.locator('input[type="checkbox"]').first();
    await checkbox.waitFor({ state: 'attached' });
    if (await checkbox.isDisabled()) {
      // Fallback for when a plain scrollTop assignment doesn't fire the
      // component's own scroll listener: scroll via real wheel events.
      const box = await page.locator('.cg-text').boundingBox();
      await page.mouse.move(box.x + box.width / 2, box.y + box.height / 2);
      for (let i = 0; i < 15; i++) {
        await page.mouse.wheel(0, 400);
        await page.waitForTimeout(100);
      }
    }
    // The checkbox's own hit target is covered by its label, so the label
    // is what actually needs the click.
    await page.locator('.cg-check').click({ force: true, timeout: 5000 });
    await page.waitForTimeout(300);
    await page.locator('button:has-text("Continue")').first().click();
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1000);
  }

  await page.screenshot({ path: `${SHOT_DIR}/02_post_login.png`, fullPage: true });

  const url = page.url();
  const hasAuthToken = await page.evaluate(() => !!localStorage.getItem('token'));
  const storedUser = await page.evaluate(() => {
    try { return JSON.parse(localStorage.getItem('user')); } catch (e) { return null; }
  });

  console.log('[6/6] Result');
  console.log('  URL after login:', url);
  console.log('  localStorage token present:', hasAuthToken);
  console.log('  stored user role:', storedUser && storedUser.role);
  if (pageErrors.length) console.log('  uncaught page errors:', pageErrors);

  await browser.close();

  if (!hasAuthToken || !storedUser || storedUser.role !== 'gcu_staff') {
    console.error('FAIL: login did not produce an authenticated gcu_staff session.');
    process.exit(1);
  }
  if (/\/login/.test(url)) {
    console.error('FAIL: still on the login page after submitting valid credentials.');
    process.exit(1);
  }
  console.log('PASS: GCU staff can sign in through the real login flow and reach an authenticated session.');
}

main().catch((err) => {
  console.error('FAIL:', err);
  process.exit(1);
});
