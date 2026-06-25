<?php

/*
 * SPDX-License-Identifier: ISC
 * SPDX-FileCopyrightText: (c) Respect Project Contributors
 * SPDX-FileContributor: Alexandre Gomes Gaigalas <alganet@gmail.com>
 */

declare(strict_types=1);

namespace Respect\Parameter;

use ReflectionFunctionAbstract;

interface ParameterResolver
{
    /**
     * Resolve the arguments for a function/constructor into an ordered, ready-to-splat list.
     *
     * @param array<int|string, mixed> $arguments
     *
     * @return list<mixed>
     */
    public function resolve(ReflectionFunctionAbstract $reflection, array $arguments): array;
}
