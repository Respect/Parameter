<?php

/*
 * SPDX-License-Identifier: ISC
 * SPDX-FileCopyrightText: (c) Respect Project Contributors
 */

declare(strict_types=1);

namespace Respect\Parameter\Test\Fixtures;

/**
 * Counts instantiations so tests can assert that PHP 8.1+ `new Foo()`
 * parameter defaults are not eagerly evaluated when the spec is built.
 */
final class ExpensiveDefaultService
{
    public static int $instances = 0;

    public function __construct()
    {
        ++self::$instances;
    }
}
