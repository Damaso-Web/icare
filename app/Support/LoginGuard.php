<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

// Counts wrong passwords in a row for one sign-in name (an email or a
// Student ID) and locks that name for a short while after too many.
//
// It counts by the name typed in, whether or not such an account exists, so
// the answers it gives cannot be used to find out which accounts are real.
class LoginGuard
{
    public const MAX_ATTEMPTS = 5;
    public const LOCK_MINUTES = 5;

    private string $key;

    public function __construct(string $kind, string $identifier)
    {
        $this->key = 'login_guard:' . $kind . ':' . sha1(strtolower(trim($identifier)));
    }

    /** Minutes left on the lock, or 0 when sign-in is allowed. */
    public function lockedMinutes(): int
    {
        $until = Cache::get($this->key . ':lock');
        if (!$until || $until <= time()) {
            return 0;
        }

        return (int) ceil(($until - time()) / 60);
    }

    /** Records one wrong attempt. Returns how many are left before the lock (0 = now locked). */
    public function fail(): int
    {
        $count = (int) Cache::get($this->key . ':count', 0) + 1;

        if ($count >= self::MAX_ATTEMPTS) {
            Cache::put($this->key . ':lock', time() + self::LOCK_MINUTES * 60, now()->addMinutes(self::LOCK_MINUTES));
            Cache::forget($this->key . ':count');
            return 0;
        }

        // The count itself is forgotten after a quiet half hour.
        Cache::put($this->key . ':count', $count, now()->addMinutes(30));

        return self::MAX_ATTEMPTS - $count;
    }

    public function clear(): void
    {
        Cache::forget($this->key . ':count');
        Cache::forget($this->key . ':lock');
    }

    public static function lockedMessage(int $minutes): string
    {
        return 'Too many incorrect attempts. Sign-in is locked for ' . $minutes . ' more minute' . ($minutes === 1 ? '' : 's') . '.';
    }

    /** Text added to the "invalid" message after a wrong attempt. */
    public static function attemptsMessage(int $left): string
    {
        return $left === 0
            ? ' Too many incorrect attempts - sign-in is now locked for ' . self::LOCK_MINUTES . ' minutes.'
            : " {$left} attempt" . ($left === 1 ? '' : 's') . ' left before sign-in is locked for ' . self::LOCK_MINUTES . ' minutes.';
    }
}
