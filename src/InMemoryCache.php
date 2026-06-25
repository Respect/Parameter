<?php

/*
 * SPDX-License-Identifier: ISC
 * SPDX-FileCopyrightText: (c) Respect Project Contributors
 */

declare(strict_types=1);

namespace Respect\Parameter;

use DateInterval;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;

use function array_key_exists;
use function is_string;

/**
 * In-memory PSR-16 cache backed by a PHP array.
 *
 * Ships with the package so users get a working spec cache out of the box: pass it as the
 * second argument to {@see Resolver} and per-callable parameter introspection is memoized
 * for the lifetime of the cache instance, with no external cache dependency required.
 *
 * The cache lives for the lifetime of this object: it is not shared across processes and is
 * lost when the object is garbage-collected. For longer-lived or shared caching, use a real
 * PSR-16 implementation (Symfony Cache, PSR-16 adapter over APCu, etc.).
 *
 * TTLs are accepted for PSR-16 conformance but ignored: this is a process-local cache, so
 * entries never expire on their own. Call {@see clear()} or {@see delete()} to remove entries.
 *
 * Not marked final so test doubles and integrations can subclass it to add instrumentation
 * (e.g. hit/miss counters) without re-implementing the whole contract.
 */
class InMemoryCache implements CacheInterface
{
    /** @var array<string, mixed> */
    protected array $store = [];

    public function get(string $key, mixed $default = null): mixed
    {
        return array_key_exists($key, $this->store) ? $this->store[$key] : $default;
    }

    public function set(string $key, mixed $value, DateInterval|int|null $ttl = null): bool
    {
        $this->store[$key] = $value;

        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->store[$key]);

        return true;
    }

    public function clear(): bool
    {
        $this->store = [];

        return true;
    }

    /**
     * @param iterable<string> $keys
     *
     * @return iterable<string, mixed>
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $out = [];
        foreach ($keys as $key) {
            $out[$key] = $this->get($key, $default);
        }

        return $out;
    }

    /**
     * @param iterable<string, mixed> $values
     *
     * @throws InvalidArgumentException When a key is not a non-empty string.
     */
    public function setMultiple(iterable $values, DateInterval|int|null $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            /** @phpstan-ignore function.alreadyNarrowedType (defensive: callers may pass int-keyed arrays that PHP upcasts to int on iteration) */
            if (!is_string($key) || $key === '') {
                throw new InvalidCacheKey('Cache keys must be non-empty strings.');
            }

            $this->store[$key] = $value;
        }

        return true;
    }

    /** @param iterable<string> $keys */
    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            unset($this->store[$key]);
        }

        return true;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->store);
    }
}
