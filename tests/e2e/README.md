# System (E2E) tests

These use Playwright to drive a real Chromium browser against a running
copy of the app - unlike `tests/Feature` (PHPUnit), they're not run as
part of `php artisan test` because they need the backend and frontend
actually serving, not just the framework's in-process test client.

## One-time setup

```
npm install -D playwright
npx playwright install chromium   # only if Chromium isn't already present
```

## Running `login.e2e.cjs`

1. Point a **disposable** SQLite database at a throwaway `.env` (never run
   this against your real dev/production database - it creates a test
   account):

   ```
   DB_CONNECTION=sqlite
   DB_DATABASE=/absolute/path/to/database/e2e_test.sqlite
   APP_ENV=local
   APP_URL=http://127.0.0.1:8099
   MAIL_MAILER=array
   ```

2. Migrate and seed the E2E account:

   ```
   touch database/e2e_test.sqlite
   php artisan migrate:fresh --force
   php artisan tinker --execute="
     \App\Models\User::create([
       'name' => 'E2E GCU Tester', 'first_name' => 'E2E', 'last_name' => 'Tester',
       'email' => 'e2e.gcu@icare.test', 'password' => bcrypt('E2ePassw0rd!'),
       'role' => 'gcu_staff', 'is_active' => true,
     ]);
   "
   ```

3. In one terminal: `php artisan serve --host=127.0.0.1 --port=8099`
4. In another: `npm run dev`
5. Then: `node tests/e2e/login.e2e.cjs`

Screenshots from the run are written to `tests/e2e/screenshots/` (gitignored)
so a failure can be inspected without re-running the browser.

## What it checks

Signs in as a GCU staff account through the actual UI (Login Choice ->
OSS Personnel -> credentials -> the first-login Confidentiality Agreement
consent modal) and asserts the session actually comes out authenticated
(an auth token and the right role in `localStorage`), rather than just
asserting the login form exists.