<?php

/*
 * SPDX-License-Identifier: ISC
 * SPDX-FileCopyrightText: (c) Respect Project Contributors
 * SPDX-FileContributor: Alexandre Gomes Gaigalas <alganet@gmail.com>
 * SPDX-FileContributor: Henrique Moody <henriquemoody@gmail.com>
 */

declare(strict_types=1);

namespace Respect\Parameter;

use ReflectionFunctionAbstract;

interface Resolver
{
    /**
     * Resolve parameters for a function/constructor from positional arguments.
     *
     * For each parameter, tries in order: positional argument of matching type,
     * container match by type, next positional argument, default value, null.
     *
     * @param array<int, mixed> $arguments User-provided positional arguments
     *
     * @return array<int, mixed>|array<string, mixed> Resolved arguments keyed by parameter name
     */
    public function resolve(ReflectionFunctionAbstract $reflection, array $arguments): array;

    /**
     * Resolve parameters from explicit named arguments.
     *
     * Named arguments take precedence, gaps are filled from the container by
     * type, then by default value, then null.
     *
     * @param array<string, mixed> $namedArgs
     *
     * @return array<string, mixed> Resolved arguments keyed by parameter name
     */
    public function resolveNamed(ReflectionFunctionAbstract $reflection, array $namedArgs): array;
}
