<?php

/*
 * SPDX-License-Identifier: ISC
 * SPDX-FileCopyrightText: (c) Respect Project Contributors
 * SPDX-FileContributor: Alexandre Gomes Gaigalas <alganet@gmail.com>
 */

declare(strict_types=1);

namespace Respect\Parameter\Test\Fixtures;

final class NamedConsumer
{
    public function __construct(
        public readonly string $username,
        public readonly string $password,
        public readonly int $port = 3306,
    ) {
    }
}
