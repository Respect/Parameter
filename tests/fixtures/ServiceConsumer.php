<?php

/*
 * SPDX-License-Identifier: ISC
 * SPDX-FileCopyrightText: (c) Respect Project Contributors
 * SPDX-FileContributor: Alexandre Gomes Gaigalas <alganet@gmail.com>
 */

declare(strict_types=1);

namespace Respect\Parameter\Test\Fixtures;

final class ServiceConsumer
{
    public function __construct(
        public readonly SampleService $service,
        public readonly string $value,
        public readonly int $number = 42,
    ) {
    }
}
