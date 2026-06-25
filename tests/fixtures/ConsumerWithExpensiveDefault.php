<?php

/*
 * SPDX-License-Identifier: ISC
 * SPDX-FileCopyrightText: (c) Respect Project Contributors
 */

declare(strict_types=1);

namespace Respect\Parameter\Test\Fixtures;

/**
 * Has a `new ExpensiveDefaultService()` default that must NOT be constructed
 * while the resolver builds/caches the parameter spec — only when the default
 * branch is actually taken.
 */
final class ConsumerWithExpensiveDefault
{
    public function __construct(
        public readonly string $value,
        public readonly ExpensiveDefaultService|null $service = new ExpensiveDefaultService(),
    ) {
    }
}
