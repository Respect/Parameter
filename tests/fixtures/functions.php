<?php

/*
 * SPDX-License-Identifier: ISC
 * SPDX-FileCopyrightText: (c) Respect Project Contributors
 * SPDX-FileContributor: Henrique Moody <henriquemoody@gmail.com>
 */

declare(strict_types=1);

// phpcs:disable Squiz.Functions.GlobalFunction.Found

namespace Respect\Parameter\Test\Fixtures;

function namedFunctionWithService(string $name, SampleService $service): bool
{
    return true;
}

// phpcs:ignore SlevomatCodingStandard.PHP.RequireExplicitAssertion.RequiredExplicitAssertion
function functionWithNonExistentType(NonExistentClass123 $x): bool // @phpstan-ignore class.notFound
{
    return true;
}
