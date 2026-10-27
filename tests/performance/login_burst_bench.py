import asyncio
import statistics
import time
import httpx

BASE_URL = "http://127.0.0.1:8099"
CONCURRENCY = 20
TOTAL_REQUESTS = 100


async def one_request(client: httpx.AsyncClient, sem: asyncio.Semaphore):
    async with sem:
        start = time.perf_counter()
        resp = await client.post(
            f"{BASE_URL}/api/login",
            json={"email": "e2e.gcu@icare.test", "password": "E2ePassw0rd!"},
        )
        elapsed = time.perf_counter() - start
        return elapsed, resp.status_code


async def main():
    sem = asyncio.Semaphore(CONCURRENCY)
    async with httpx.AsyncClient(timeout=30) as client:
        start = time.perf_counter()
        results = await asyncio.gather(*[one_request(client, sem) for _ in range(TOTAL_REQUESTS)])
        total_elapsed = time.perf_counter() - start

    times = sorted(r[0] for r in results)
    statuses = [r[1] for r in results]
    n = len(times)

    def pct(p):
        idx = min(n - 1, int(round(p / 100 * n)) - 1)
        return times[max(0, idx)]

    print(f"Requests: {n}, concurrency: {CONCURRENCY}")
    print(f"Wall-clock total: {total_elapsed:.2f}s ({n / total_elapsed:.1f} req/s)")
    print(f"Min: {times[0]*1000:.0f} ms")
    print(f"Mean: {statistics.mean(times)*1000:.0f} ms")
    print(f"Median (p50): {pct(50)*1000:.0f} ms")
    print(f"p95: {pct(95)*1000:.0f} ms")
    print(f"p99: {pct(99)*1000:.0f} ms")
    print(f"Max: {times[-1]*1000:.0f} ms")
    from collections import Counter
    print(f"Status code counts: {dict(Counter(statuses))}")
    print("(429 = iCARE's own per-account/per-IP login rate limiter kicking in - see RouteServiceProvider)")
    print(f"All requests got an HTTP response (no timeouts/connection errors): {n == TOTAL_REQUESTS}")


if __name__ == "__main__":
    asyncio.run(main())