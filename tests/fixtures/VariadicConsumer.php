<?php

/*
 * SPDX-License-Identifier: ISC
 * SPDX-FileCopyrightText: (c) Respect Project Contributors
 * SPDX-FileContributor: Alexandre Gomes Gaigalas <alganet@gmail.com>
 */

declare(strict_types=1);

namespace Respect\Parameter\Test\Fixtures;

final class VariadicConsumer
{
    /** @var array<array-key, int> */
    public readonly array $numbers;

    public function __construct(public readonly SampleService $service, int ...$numbers)
    {
        $this->numbers = $numbers;
    }
}
