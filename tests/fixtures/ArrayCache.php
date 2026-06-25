<?php

/*
 * SPDX-License-Identifier: ISC
 * SPDX-FileCopyrightText: (c) Respect Project Contributors
 */

declare(strict_types=1);

namespace Respect\Parameter\Test\Fixtures;

use DateInterval;
use Respect\Parameter\InMemoryCache;

use function array_key_exists;

/**
 * Test-only subclass of {@see InMemoryCache} that records set/get counts so tests can
 * assert cache hits vs misses against the production implementation.
 */
final class ArrayCache extends InMemoryCache
{
    public int $sets = 0;

    public int $hits = 0;

    public int $misses = 0;

    public function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->store)) {
            ++$this->hits;

            return $this->store[$key];
        }

        ++$this->misses;

        return $default;
    }

    public function set(string $key, mixed $value, DateInterval|int|null $ttl = null): bool
    {
        ++$this->sets;

        return parent::set($key, $value, $ttl);
    }

    /** @param iterable<string, mixed> $values */
    public function setMultiple(iterable $values, DateInterval|int|null $ttl = null): bool
    {
        // Delegates to parent::set so we increment $this->sets per entry exactly
        // once (parent::setMultiple writes the store directly, bypassing set()).
        foreach ($values as $key => $value) {
            $this->set((string) $key, $value, $ttl);
        }

        return true;
    }
}
