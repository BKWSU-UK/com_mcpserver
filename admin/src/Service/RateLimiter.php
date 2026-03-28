<?php

declare(strict_types=1);

namespace Joomla\Component\Mcpserver\Administrator\Service;

defined('_JEXEC') or die;

use Psr\SimpleCache\CacheInterface;

class RateLimiter
{
    private CacheInterface $cache;
    private int $maxRequests;
    private int $windowSeconds;

    public function __construct(CacheInterface $cache, int $maxRequests = 60, int $windowSeconds = 60)
    {
        $this->cache = $cache;
        $this->maxRequests = $maxRequests;
        $this->windowSeconds = $windowSeconds;
    }

    public function checkLimit(string $identifier): ?array
    {
        $key = 'ratelimit:' . md5($identifier);
        $current = $this->cache->get($key, ['count' => 0, 'reset' => time() + $this->windowSeconds]);

        if (!is_array($current)) {
            $current = ['count' => 0, 'reset' => time() + $this->windowSeconds];
        }

        if ($current['reset'] < time()) {
            $current = ['count' => 0, 'reset' => time() + $this->windowSeconds];
        }

        $current['count']++;
        $this->cache->set($key, $current, $this->windowSeconds);

        if ($current['count'] > $this->maxRequests) {
            return [
                'limited' => true,
                'retry_after' => $current['reset'] - time(),
            ];
        }

        return null;
    }
}

