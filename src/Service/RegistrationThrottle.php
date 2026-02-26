<?php

namespace App\Service;

use Psr\Cache\CacheItemPoolInterface;

class RegistrationThrottle
{
    public function __construct(
        private readonly CacheItemPoolInterface $cache,
    ) {
    }

    /**
     * @return array{accepted: bool, retry_after: int|null}
     */
    public function consume(string $key, int $limit = 20, int $windowSeconds = 60): array
    {
        $cacheKey = 'clawhq_reg_limit_'.sha1($key);
        $item = $this->cache->getItem($cacheKey);

        $now = time();
        $bucket = [
            'count' => 0,
            'expires_at' => $now + $windowSeconds,
        ];

        if ($item->isHit()) {
            $stored = $item->get();
            if (is_array($stored) && isset($stored['count'], $stored['expires_at'])) {
                $bucket = [
                    'count' => (int) $stored['count'],
                    'expires_at' => (int) $stored['expires_at'],
                ];
            }
        }

        if ($bucket['expires_at'] <= $now) {
            $bucket = [
                'count' => 0,
                'expires_at' => $now + $windowSeconds,
            ];
        }

        if ($bucket['count'] >= $limit) {
            return [
                'accepted' => false,
                'retry_after' => max(1, $bucket['expires_at'] - $now),
            ];
        }

        $bucket['count']++;
        $item->set($bucket);
        $item->expiresAfter(max(1, $bucket['expires_at'] - $now));
        $this->cache->save($item);

        return [
            'accepted' => true,
            'retry_after' => null,
        ];
    }
}
