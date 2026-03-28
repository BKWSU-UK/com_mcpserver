<?php

declare(strict_types=1);

namespace Joomla\Component\Mcpserver\Administrator\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Psr\SimpleCache\CacheInterface;
use DateInterval;

class JoomlaCache implements CacheInterface
{
    private $cache;
    private string $group;

    public function __construct(string $group = 'com_mcpserver')
    {
        $this->group = $group;
        $this->cache = Factory::getCache($group, '');
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $data = $this->cache->get($key);
        return $data !== false ? $data : $default;
    }

    public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
    {
        if ($ttl !== null) {
            $seconds = $ttl instanceof DateInterval
                ? (int) ((new \DateTimeImmutable('now'))->add($ttl)->getTimestamp() - time())
                : $ttl;
            if ($seconds > 0) {
                $this->cache->setLifeTime($seconds);
            }
        }

        return $this->cache->store($value, $key);
    }

    public function delete(string $key): bool
    {
        return $this->cache->remove($key);
    }

    public function clear(): bool
    {
        return $this->cache->clean($this->group);
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get((string) $key, $default);
        }
        return $result;
    }

    public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set((string) $key, $value, $ttl);
        }
        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete((string) $key);
        }
        return true;
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function deleteByPrefix(string $prefix): void
    {
        $cachedKeys = $this->cache->getAll();

        if (is_array($cachedKeys)) {
            foreach ($cachedKeys as $key => $value) {
                if (str_starts_with($key, $prefix)) {
                    $this->cache->remove($key);
                }
            }
        }
    }
}


