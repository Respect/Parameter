<?php

/*
 * SPDX-License-Identifier: ISC
 * SPDX-FileCopyrightText: (c) Respect Project Contributors
 * SPDX-FileContributor: Alexandre Gomes Gaigalas <alganet@gmail.com>
 * SPDX-FileContributor: Henrique Moody <henriquemoody@gmail.com>
 */

declare(strict_types=1);

namespace Respect\Parameter\Test\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use Respect\Parameter\Reflector;
use Respect\Parameter\Test\Fixtures\ArrayContainer;
use Respect\Parameter\Test\Fixtures\SampleService;
use Respect\Parameter\Test\Fixtures\ServiceConsumer;

#[CoversClass(Reflector::class)]
final class ReflectorTest extends TestCase
{
    #[Test]
    public function itShouldDetectAcceptedType(): void
    {
        $constructor = (new ReflectionClass(ServiceConsumer::class))->getConstructor();
        self::assertNotNull($constructor);

        self::assertTrue(Reflector::acceptsType($constructor, SampleService::class));
        self::assertFalse(Reflector::acceptsType($constructor, ArrayContainer::class));
    }

    #[Test]
    public function itShouldReflectClosure(): void
    {
        $fn = static function (string $a): string {
            return $a;
        };

        $reflection = Reflector::reflectCallable($fn);

        self::assertInstanceOf(ReflectionFunction::class, $reflection);
        self::assertSame('a', $reflection->getParameters()[0]->getName());
    }

    #[Test]
    public function itShouldReflectArrayCallable(): void
    {
        $reflection = Reflector::reflectCallable([new ArrayContainer([]), 'has']);

        self::assertInstanceOf(ReflectionMethod::class, $reflection);
        self::assertSame('has', $reflection->getName());
    }

    #[Test]
    public function itShouldReflectInvocableObject(): void
    {
        $invocable = new class () {
            public function __invoke(int $x): int
            {
                return $x;
            }
        };

        $reflection = Reflector::reflectCallable($invocable);

        self::assertInstanceOf(ReflectionMethod::class, $reflection);
        self::assertSame('__invoke', $reflection->getName());
        self::assertSame('x', $reflection->getParameters()[0]->getName());
    }

    #[Test]
    public function itShouldReflectNamedFunction(): void
    {
        $reflection = Reflector::reflectCallable('strlen');

        self::assertInstanceOf(ReflectionFunction::class, $reflection);
        self::assertSame('strlen', $reflection->getName());
    }

    #[Test]
    public function itShouldReflectStaticMethodString(): void
    {
        $reflection = Reflector::reflectCallable('DateTime::createFromFormat');

        self::assertInstanceOf(ReflectionMethod::class, $reflection);
        self::assertSame('createFromFormat', $reflection->getName());
    }
}
