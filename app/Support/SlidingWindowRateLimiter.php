<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Redis;

final class SlidingWindowRateLimiter
{
    private const WINDOW_SECONDS = 60;

    /**
     * Attempt to acquire a rate limit token using sliding window algorithm
     *
     * @param string $key Unique identifier (e.g., "tenant:uuid:email")
     * @param int $limitPerMinute Maximum requests allowed per minute
     * @return bool True if request is allowed, false if rate limit exceeded
     */
    public function acquire(string $key, int $limitPerMinute): bool
    {
        $now = microtime(true);
        $windowStart = $now - self::WINDOW_SECONDS;
        $redisKey = "ratelimit:sliding:{$key}";

        // Use Redis pipeline for atomic operations
        $results = Redis::pipeline(function ($pipe) use ($redisKey, $windowStart, $now, $limitPerMinute) {
            // Remove timestamps outside the sliding window
            $pipe->zremrangebyscore($redisKey, '-inf', (string) $windowStart);

            // Count requests in current window
            $pipe->zcard($redisKey);

            // Add current request timestamp
            $pipe->zadd($redisKey, $now, (string) $now);

            // Set expiration to prevent memory leaks
            $pipe->expire($redisKey, self::WINDOW_SECONDS + 10);
        });

        $currentCount = (int) $results[1];

        // Check if adding this request would exceed limit
        if ($currentCount >= $limitPerMinute) {
            // Remove the request we just added since we're rejecting it
            Redis::zrem($redisKey, (string) $now);
            return false;
        }

        return true;
    }

    /**
     * Get current request count in the sliding window
     *
     * @param string $key Rate limit key
     * @return int Current request count
     */
    public function getCurrentCount(string $key): int
    {
        $now = microtime(true);
        $windowStart = $now - self::WINDOW_SECONDS;
        $redisKey = "ratelimit:sliding:{$key}";

        Redis::zremrangebyscore($redisKey, '-inf', (string) $windowStart);
        return (int) Redis::zcard($redisKey);
    }

    /**
     * Get remaining attempts for a key
     *
     * @param string $key Rate limit key
     * @param int $limitPerMinute Rate limit threshold
     * @return int Remaining attempts
     */
    public function remaining(string $key, int $limitPerMinute): int
    {
        $current = $this->getCurrentCount($key);
        return max(0, $limitPerMinute - $current);
    }

    /**
     * Reset rate limit for a key
     *
     * @param string $key Rate limit key
     * @return void
     */
    public function reset(string $key): void
    {
        Redis::del("ratelimit:sliding:{$key}");
    }
}
