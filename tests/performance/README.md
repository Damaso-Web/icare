# Performance scripts (not JMeter/K6)

The original Methodology plan named JMeter/K6 for performance testing.
Neither tool is usable in the environment these were developed in (no way
to fetch their installers), so these are lightweight, honest substitutes:
plain Python + `httpx`, hitting the real `/api/login` endpoint with
concurrent requests and reporting min/mean/median/p95/max latency. They
are not a replacement for a proper load-testing tool in a production
readiness review, but they do exercise real code (bcrypt hashing, the
DB, Sanctum token issuance, the login rate limiter) rather than reporting
invented numbers.

## Setup

Same disposable SQLite + seeded accounts as `tests/e2e/README.md`. This
script expects several seeded accounts (`e2e.bench1@icare.test` through
`e2e.bench15@icare.test`, all password `E2ePassw0rd!`) in addition to the
single `e2e.gcu@icare.test` account, so each concurrent request uses a
different account and doesn't trip the per-account rate limit on its own.

```
php artisan tinker --execute="
for (\$i = 1; \$i <= 15; \$i++) {
    \App\Models\User::create([
        'name' => \"E2E Bench \$i\", 'first_name' => 'E2E', 'last_name' => \"Bench\$i\",
        'email' => \"e2e.bench\$i@icare.test\", 'password' => bcrypt('E2ePassw0rd!'),
        'role' => 'gcu_staff', 'is_active' => true,
    ]);
}
"
```

Then, with `php artisan serve --port=8099` running:

```
pip install httpx
python3 tests/performance/login_latency_bench.py   # clean latency read, stays under the rate limit
python3 tests/performance/login_burst_bench.py      # deliberately bursts past it - security evidence, see below
```

## What each one measured (recorded run, local sandbox hardware - not
## representative of production hosting, but real numbers from real code)

**`login_latency_bench.py`** - 15 requests, concurrency 4, one account
each, all succeeding (200):

- Median: ~1.2s, p95: ~1.2s, max: ~1.3s, min: ~0.4s

The latency here is dominated by bcrypt's deliberately-slow password
hashing (by design - that's what makes brute-forcing expensive), not by
routing or query overhead.

**`login_burst_bench.py`** - 100 requests at once, same single account,
concurrency 20: **exactly 5 succeeded (200) and 95 were rejected (429)**,
matching `RouteServiceProvider`'s configured `5/minute per account+IP`
login limiter precisely. This is also the evidence cited in place of
OWASP ZAP for brute-force protection: it is not a vulnerability scan, but
it does confirm the app's own rate limiter actually works under
concurrent load, not just in a single-request PHPUnit test.