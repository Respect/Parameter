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
use Respect\Parameter\ParameterResolver;
use Respect\Parameter\Resolver;
use Respect\Parameter\Test\Fixtures\ArrayContainer;
use Respect\Parameter\Test\Fixtures\SampleService;
use Respect\Parameter\Test\Fixtures\ServiceConsumer;
use Respect\Parameter\Test\Fixtures\VariadicConsumer;

#[CoversClass(Resolver::class)]
final class ResolverTest extends TestCase
{
    #[Test]
    public function itShouldImplementParameterResolver(): void
    {
        self::assertInstanceOf(ParameterResolver::class, new Resolver(new ArrayContainer()));
    }

    #[Test]
    public function itShouldResolveByType(): void
    {
        $service = new SampleService();
        $resolver = new Resolver(new ArrayContainer([SampleService::class => $service]));

        $args = $resolver->resolve($this->constructorOf(ServiceConsumer::class), ['hello']);

        self::assertSame([$service, 'hello', 42], $args);
    }

    #[Test]
    public function itShouldAllowUserOverride(): void
    {
        $default = new SampleService();
        $explicit = new SampleService();
        $resolver = new Resolver(new ArrayContainer([SampleService::class => $default]));

        $args = $resolver->resolve($this->constructorOf(ServiceConsumer::class), [$explicit, 'hello']);

        self::assertSame($explicit, $args[0]);
        self::assertSame('hello', $args[1]);
    }

    #[Test]
    public function itShouldFallThroughToPositionalArgs(): void
    {
        $resolver = new Resolver(new ArrayContainer());

        $args = $resolver->resolve($this->constructorOf(ServiceConsumer::class), ['positional']);

        self::assertSame('positional', $args[0]);
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
    public function itShouldKeepDeprecatedResolveNamedAsAnAliasOfResolve(): void
    {
        $resolver = new Resolver(new ArrayContainer([SampleService::class => new SampleService()]));
        $constructor = $this->constructorOf(ServiceConsumer::class);

        self::assertSame(
            $resolver->resolve($constructor, ['value' => 'explicit']),
            $resolver->resolveNamed($constructor, ['value' => 'explicit']),
        );
    }

    #[Test]
    public function itShouldExpandVariadicArguments(): void
    {
        $service = new SampleService();
        $resolver = new Resolver(new ArrayContainer([SampleService::class => $service]));

        $args = $resolver->resolve($this->constructorOf(VariadicConsumer::class), [1, 2, 3]);

        self::assertSame([$service, 1, 2, 3], $args);
    }

    #[Test]
    public function itShouldSupplyVariadicElementByName(): void
    {
        $service = new SampleService();
        $resolver = new Resolver(new ArrayContainer([SampleService::class => $service]));

        $args = $resolver->resolve($this->constructorOf(VariadicConsumer::class), ['numbers' => 9]);
        $consumer = (new ReflectionClass(VariadicConsumer::class))->newInstanceArgs($args);

        self::assertSame([$service, 9], $args);
        self::assertSame([9], $consumer->numbers);
    }

    #[Test]
    public function itShouldResolveArgumentsReadyToSplatIntoVariadicConstructor(): void
    {
        $service = new SampleService();
        $resolver = new Resolver(new ArrayContainer([SampleService::class => $service]));

        $args = $resolver->resolve($this->constructorOf(VariadicConsumer::class), [1, 2, 3]);
        $consumer = (new ReflectionClass(VariadicConsumer::class))->newInstanceArgs($args);

        self::assertSame($service, $consumer->service);
        self::assertSame([1, 2, 3], $consumer->numbers);
    }

    #[Test]
    public function itShouldResolveNamedArgumentsReadyToSplat(): void
    {
        $service = new SampleService();
        $resolver = new Resolver(new ArrayContainer([SampleService::class => $service]));

        $args = $resolver->resolve($this->constructorOf(ServiceConsumer::class), ['value' => 'hi', 'number' => 7]);
        $consumer = (new ReflectionClass(ServiceConsumer::class))->newInstanceArgs($args);

        self::assertSame($service, $consumer->service);
        self::assertSame('hi', $consumer->value);
        self::assertSame(7, $consumer->number);
    }

    /** @param class-string $class */
    private function constructorOf(string $class): ReflectionMethod
    {
        $constructor = (new ReflectionClass($class))->getConstructor();
        self::assertNotNull($constructor);

        return $constructor;
    }
}
