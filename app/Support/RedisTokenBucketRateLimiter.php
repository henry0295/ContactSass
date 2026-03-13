<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Redis;

final class RedisTokenBucketRateLimiter
{
    public function acquire(string $key, int $limitPerMinute): bool
    {
        $windowKey = sprintf('ratelimit:%s:%s', $key, now()->format('YmdHi'));
        $count = (int) Redis::incr($windowKey);
        Redis::expire($windowKey, 90);

        return $count <= $limitPerMinute;
    }
}
