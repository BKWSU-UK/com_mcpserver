<?php

declare(strict_types=1);

namespace Joomla\Component\Mcpserver\Administrator\Service;

defined('_JEXEC') or die;

use DateInterval;
use DateTimeInterface;
use Psr\SimpleCache\CacheInterface;

class SimpleArrayCache implements CacheInterface
{
    /** @var array<string, array{value:mixed, expiresAt:int|null}> */
    private array $store = [];

    public function get(string $key, mixed $default = null): mixed
    {
        $this->assertStringKey($key);
        if (!isset($this->store[$key])) {
            return $default;
        }
        $entry = $this->store[$key];
        if ($entry['expiresAt'] !== null && $entry['expiresAt'] < time()) {
            unset($this->store[$key]);
            return $default;
        }
        return $entry['value'];
    }

    public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
    {
        $this->assertStringKey($key);
        $expiresAt = $this->normalizeTtlToTimestamp($ttl);
        $this->store[$key] = [
            'value' => $value,
            'expiresAt' => $expiresAt,
        ];
        return true;
    }

    public function delete(string $key): bool
    {
        $this->assertStringKey($key);
        unset($this->store[$key]);
        return true;
    }

    public function clear(): bool
    {
        $this->store = [];
        return true;
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
        $this->assertStringKey($key);
        if (!isset($this->store[$key])) {
            return false;
        }
        $entry = $this->store[$key];
        if ($entry['expiresAt'] !== null && $entry['expiresAt'] < time()) {
            unset($this->store[$key]);
            return false;
        }
        return true;
    }

    private function assertStringKey(string $key): void
    {
        if ($key === '') {
            throw new \InvalidArgumentException('Cache key must be a non-empty string');
        }
    }

    private function normalizeTtlToTimestamp(null|int|DateInterval $ttl): ?int
    {
        if ($ttl === null) {
            return null;
        }
        $seconds = $ttl instanceof DateInterval
            ? (new \DateTimeImmutable('now'))->add($ttl)->getTimestamp() - time()
            : $ttl;
        if ($seconds <= 0) {
            return time();
        }
        return time() + $seconds;
    }

    public function deleteByPrefix(string $prefix): void
    {
        foreach (array_keys($this->store) as $key) {
            if (str_starts_with($key, $prefix)) {
                unset($this->store[$key]);
            }
        }
    }
}


