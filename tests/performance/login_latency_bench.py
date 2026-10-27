import asyncio
import statistics
import time
import httpx

# iCARE rate-limits /api/login to 5 requests/minute per account+IP and
# 20/minute per IP (app/Providers/RouteServiceProvider.php), so a "pure
# latency" read has to stay under that cap - a separate run intentionally
# bursts past it to confirm the limiter itself works (that's the security
# evidence, recorded separately).
BASE_URL = "http://127.0.0.1:8099"
CONCURRENCY = 4
TOTAL_REQUESTS = 15  # under the 20/min per-IP cap


async def one_login(client: httpx.AsyncClient, sem: asyncio.Semaphore, email: str):
    async with sem:
        start = time.perf_counter()
        resp = await client.post(
            f"{BASE_URL}/api/login",
            json={"email": email, "password": "E2ePassw0rd!"},
        )
        elapsed = time.perf_counter() - start
        return elapsed, resp.status_code


async def main():
    sem = asyncio.Semaphore(CONCURRENCY)
    async with httpx.AsyncClient(timeout=30) as client:
        start = time.perf_counter()
        # One request per distinct account keeps every call under the
        # per-account 5/min limit even though they share an IP; all 15 stay
        # under the 20/min per-IP cap too.
        results = await asyncio.gather(*[
            one_login(client, sem, f"e2e.bench{i+1}@icare.test") for i in range(TOTAL_REQUESTS)
        ])
        total_elapsed = time.perf_counter() - start

    times = sorted(r[0] for r in results)
    statuses = [r[1] for r in results]
    n = len(times)

    def pct(p):
        idx = min(n - 1, int(round(p / 100 * n)) - 1)
        return times[max(0, idx)]

    print(f"Requests: {n}, concurrency: {CONCURRENCY} (kept under the app's own 20/min per-IP login rate limit)")
    print(f"Wall-clock total: {total_elapsed:.2f}s ({n / total_elapsed:.1f} req/s)")
    print(f"Min: {times[0]*1000:.0f} ms")
    print(f"Mean: {statistics.mean(times)*1000:.0f} ms")
    print(f"Median (p50): {pct(50)*1000:.0f} ms")
    print(f"p95: {pct(95)*1000:.0f} ms")
    print(f"Max: {times[-1]*1000:.0f} ms")
    print(f"Status codes: {sorted(set(statuses))} (200 expected)")


if __name__ == "__main__":
    asyncio.run(main())