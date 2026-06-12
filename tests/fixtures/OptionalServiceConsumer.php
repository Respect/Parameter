<?php

/*
 * SPDX-License-Identifier: ISC
 * SPDX-FileCopyrightText: (c) Respect Project Contributors
 * SPDX-FileContributor: Henrique Moody <henriquemoody@gmail.com>
 */

declare(strict_types=1);

namespace Respect\Parameter\Test\Fixtures;

final class OptionalServiceConsumer
{
    public function __construct(
        public readonly string $name = 'default',
        public readonly SampleService|null $service = null,
    ) {
    }
}
