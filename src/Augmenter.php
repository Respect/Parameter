<?php

/*
 * SPDX-License-Identifier: ISC
 * SPDX-FileCopyrightText: (c) Respect Project Contributors
 * SPDX-FileContributor: Henrique Moody <henriquemoody@gmail.com>
 */

declare(strict_types=1);

namespace Respect\Parameter;

use ReflectionFunctionAbstract;

interface Augmenter
{
    /**
     * Augment the given arguments with values for the parameters they do not already fill.
     *
     * The given arguments are authoritative: they are never rebound, reordered,
     * or padded with defaults or null. Only parameters left unfilled may gain a
     * value, added as named arguments. Variadic and builtin-typed parameters
     * are never augmented.
     *
     * @param array<int|string, mixed> $arguments Positional and/or named arguments
     *
     * @return array<int|string, mixed>
     */
    public function augment(ReflectionFunctionAbstract $reflection, array $arguments): array;
}
