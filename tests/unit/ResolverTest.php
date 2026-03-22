<?php

/*
 * SPDX-License-Identifier: ISC
 * SPDX-FileCopyrightText: (c) Respect Project Contributors
 * SPDX-FileContributor: Alexandre Gomes Gaigalas <alganet@gmail.com>
 */

declare(strict_types=1);

namespace Respect\Parameter\Test\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use Respect\Parameter\Resolver;
use Respect\Parameter\Test\Fixtures\ArrayContainer;
use Respect\Parameter\Test\Fixtures\SampleService;
use Respect\Parameter\Test\Fixtures\ServiceConsumer;

#[CoversClass(Resolver::class)]
final class ResolverTest extends TestCase
{
    #[Test]
    public function itShouldResolveByType(): void
    {
        $service = new SampleService();
        $resolver = new Resolver(new ArrayContainer([SampleService::class => $service]));

        $args = $resolver->resolve($this->constructorOf(ServiceConsumer::class), ['hello']);

        self::assertSame($service, $args['service']);
        self::assertSame('hello', $args['value']);
        self::assertSame(42, $args['number']);
    }

    #[Test]
    public function itShouldAllowUserOverride(): void
    {
        $default = new SampleService();
        $explicit = new SampleService();
        $resolver = new Resolver(new ArrayContainer([SampleService::class => $default]));

        $args = $resolver->resolve($this->constructorOf(ServiceConsumer::class), [$explicit, 'hello']);

        self::assertSame($explicit, $args['service']);
        self::assertSame('hello', $args['value']);
    }

    #[Test]
    public function itShouldFallThroughToPositionalArgs(): void
    {
        $resolver = new Resolver(new ArrayContainer());

        $args = $resolver->resolve($this->constructorOf(ServiceConsumer::class), ['positional']);

        self::assertSame('positional', $args['service']);
    }

    #[Test]
    public function itShouldPassThroughWhenNoParams(): void
    {
        $resolver = new Resolver(new ArrayContainer());
        $fn = new ReflectionFunction(static function (): void {
        });

        $args = $resolver->resolve($fn, ['a', 'b']);

        self::assertSame(['a', 'b'], $args);
    }

    #[Test]
    public function itShouldDetectAcceptedType(): void
    {
        $constructor = $this->constructorOf(ServiceConsumer::class);

        self::assertTrue(Resolver::acceptsType($constructor, SampleService::class));
        self::assertFalse(Resolver::acceptsType($constructor, ArrayContainer::class));
    }

    #[Test]
    public function itShouldReflectClosure(): void
    {
        $fn = static function (string $a): string {
            return $a;
        };

        $reflection = Resolver::reflectCallable($fn);

        self::assertInstanceOf(ReflectionFunction::class, $reflection);
        self::assertSame('a', $reflection->getParameters()[0]->getName());
    }

    #[Test]
    public function itShouldReflectArrayCallable(): void
    {
        $reflection = Resolver::reflectCallable([new ArrayContainer([]), 'has']);

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

        $reflection = Resolver::reflectCallable($invocable);

        self::assertInstanceOf(ReflectionMethod::class, $reflection);
        self::assertSame('__invoke', $reflection->getName());
        self::assertSame('x', $reflection->getParameters()[0]->getName());
    }

    #[Test]
    public function itShouldReflectNamedFunction(): void
    {
        $reflection = Resolver::reflectCallable('strlen');

        self::assertInstanceOf(ReflectionFunction::class, $reflection);
        self::assertSame('strlen', $reflection->getName());
    }

    #[Test]
    public function itShouldReflectStaticMethodString(): void
    {
        $reflection = Resolver::reflectCallable('DateTime::createFromFormat');

        self::assertInstanceOf(ReflectionMethod::class, $reflection);
        self::assertSame('createFromFormat', $reflection->getName());
    }

    #[Test]
    public function itShouldResolveNamedArgsWithPrecedenceOverContainer(): void
    {
        $service = new SampleService();
        $resolver = new Resolver(new ArrayContainer([SampleService::class => $service]));

        $args = $resolver->resolveNamed(
            $this->constructorOf(ServiceConsumer::class),
            ['value' => 'explicit'],
        );

        self::assertSame($service, $args['service']);
        self::assertSame('explicit', $args['value']);
        self::assertSame(42, $args['number']);
    }

    #[Test]
    public function itShouldResolveNamedArgsWithEmptyNamedArray(): void
    {
        $service = new SampleService();
        $resolver = new Resolver(new ArrayContainer([SampleService::class => $service]));

        $args = $resolver->resolveNamed(
            $this->constructorOf(ServiceConsumer::class),
            [],
        );

        self::assertSame($service, $args['service']);
        self::assertNull($args['value']);
        self::assertSame(42, $args['number']);
    }

    /** @param class-string $class */
    private function constructorOf(string $class): ReflectionMethod
    {
        $constructor = (new ReflectionClass($class))->getConstructor();
        self::assertNotNull($constructor);

        return $constructor;
    }
}
