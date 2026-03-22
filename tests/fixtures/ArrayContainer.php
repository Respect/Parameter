<?php

/*
 * SPDX-License-Identifier: ISC
 * SPDX-FileCopyrightText: (c) Respect Project Contributors
 * SPDX-FileContributor: Alexandre Gomes Gaigalas <alganet@gmail.com>
 */

declare(strict_types=1);

namespace Respect\Parameter\Test\Fixtures;

use Psr\Container\ContainerInterface;

use function array_key_exists;

/** Simple array-backed container for testing */
final readonly class ArrayContainer implements ContainerInterface
{
    /** @param array<string, mixed> $entries */
    public function __construct(
        private array $entries = [],
    ) {
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->entries);
    }

    public function get(string $id): mixed
    {
        return $this->entries[$id];
    }
}
