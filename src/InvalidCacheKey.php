<?php

/*
 * SPDX-License-Identifier: ISC
 * SPDX-FileCopyrightText: (c) Respect Project Contributors
 */

declare(strict_types=1);

namespace Respect\Parameter;

use Psr\SimpleCache\InvalidArgumentException;
use RuntimeException;

/**
 * Thrown by {@see InMemoryCache::setMultiple()} when a caller passes a key that
 * violates the PSR-16 key contract (non-string or empty string).
 *
 * Implements the PSR-16 `InvalidArgumentException` marker interface so callers
 * that catch `Psr\SimpleCache\InvalidArgumentException` see it as a standard
 * PSR-16 exception, while still carrying a concrete class name for typed
 * catches in application code.
 */
final class InvalidCacheKey extends RuntimeException implements InvalidArgumentException
{
}
