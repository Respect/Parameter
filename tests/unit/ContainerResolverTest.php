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
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use Respect\Parameter\ContainerResolver;
use Respect\Parameter\Resolver;
use Respect\Parameter\Test\Fixtures\ArrayContainer;
use Respect\Parameter\Test\Fixtures\SampleService;
use Respect\Parameter\Test\Fixtures\ServiceConsumer;
use Respect\Parameter\Test\Fixtures\VariadicConsumer;

#[CoversClass(ContainerResolver::class)]
final class ContainerResolverTest extends TestCase
{
    #[Test]
    public function itShouldImplementResolver(): void
    {
        self::assertInstanceOf(Resolver::class, new ContainerResolver(new ArrayContainer()));
    }

    #[Test]
    public function itShouldResolveByType(): void
    {
        $service = new SampleService();
        $resolver = new ContainerResolver(new ArrayContainer([SampleService::class => $service]));

        $args = $resolver->resolve($this->constructorOf(ServiceConsumer::class), ['hello']);

        self::assertSame([$service, 'hello', 42], $args);
    }

    #[Test]
    public function itShouldResolveByTypeDespiteOrder(): void
    {
        $service = new SampleService();
        $anotherService = new SampleService();
        $container = new ArrayContainer([
            SampleService::class => $service,
            'zoo' => $anotherService,
        ]);
        $resolver = new ContainerResolver($container);

        $args = $resolver->resolve($this->constructorOf(ServiceConsumer::class), ['hello', $container->get('zoo')]);

        self::assertSame([$anotherService, 'hello', 42], $args);
    }

    #[Test]
    public function itShouldResolveTypedArgumentDeclaredAfterScalar(): void
    {
        $service = new SampleService();
        $resolver = new ContainerResolver(new ArrayContainer());
        $fn = new ReflectionFunction(
            static fn(string $value, SampleService $service): array => [$value, $service],
        );

        $args = $resolver->resolve($fn, [$service, 'hello']);

        self::assertSame(['hello', $service], $args);
    }

    #[Test]
    public function itShouldResolveMultipleTypedArgumentsOutOfOrder(): void
    {
        $service = new SampleService();
        $container = new ArrayContainer();
        $resolver = new ContainerResolver($container);
        $fn = new ReflectionFunction(
            static fn(SampleService $service, ContainerInterface $c, string $value): array => [$service, $c, $value],
        );

        $args = $resolver->resolve($fn, ['hello', $container, $service]);

        self::assertSame([$service, $container, 'hello'], $args);
    }

    #[Test]
    public function itShouldNotConsumeScalarForUnfilledTypedParameter(): void
    {
        $service = new SampleService();
        $resolver = new ContainerResolver(new ArrayContainer());
        $fn = new ReflectionFunction(
            static fn(int $number, SampleService $service, string $value): array => [$number, $service, $value],
        );

        $args = $resolver->resolve($fn, [7, 'hello', $service]);

        self::assertSame([7, $service, 'hello'], $args);
    }

    #[Test]
    public function itShouldAllowUserOverride(): void
    {
        $default = new SampleService();
        $explicit = new SampleService();
        $resolver = new ContainerResolver(new ArrayContainer([SampleService::class => $default]));

        $args = $resolver->resolve($this->constructorOf(ServiceConsumer::class), [$explicit, 'hello']);

        self::assertSame($explicit, $args[0]);
        self::assertSame('hello', $args[1]);
    }

    #[Test]
    public function itShouldFallThroughToPositionalArgs(): void
    {
        $resolver = new ContainerResolver(new ArrayContainer());

        $args = $resolver->resolve($this->constructorOf(ServiceConsumer::class), ['positional']);

        self::assertSame('positional', $args[0]);
    }

    #[Test]
    public function itShouldPassThroughWhenNoParams(): void
    {
        $resolver = new ContainerResolver(new ArrayContainer());
        $fn = new ReflectionFunction(static function (): void {
        });

        $args = $resolver->resolve($fn, ['a', 'b']);

        self::assertSame(['a', 'b'], $args);
    }

    #[Test]
    public function itShouldDetectAcceptedType(): void
    {
        $constructor = $this->constructorOf(ServiceConsumer::class);

        self::assertTrue(ContainerResolver::acceptsType($constructor, SampleService::class));
        self::assertFalse(ContainerResolver::acceptsType($constructor, ArrayContainer::class));
    }

    #[Test]
    public function itShouldReflectClosure(): void
    {
        $fn = static function (string $a): string {
            return $a;
        };

        $reflection = ContainerResolver::reflectCallable($fn);

        self::assertInstanceOf(ReflectionFunction::class, $reflection);
        self::assertSame('a', $reflection->getParameters()[0]->getName());
    }

    #[Test]
    public function itShouldReflectArrayCallable(): void
    {
        $reflection = ContainerResolver::reflectCallable([new ArrayContainer([]), 'has']);

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

        $reflection = ContainerResolver::reflectCallable($invocable);

        self::assertInstanceOf(ReflectionMethod::class, $reflection);
        self::assertSame('__invoke', $reflection->getName());
        self::assertSame('x', $reflection->getParameters()[0]->getName());
    }

    #[Test]
    public function itShouldReflectNamedFunction(): void
    {
        $reflection = ContainerResolver::reflectCallable('strlen');

        self::assertInstanceOf(ReflectionFunction::class, $reflection);
        self::assertSame('strlen', $reflection->getName());
    }

    #[Test]
    public function itShouldReflectStaticMethodString(): void
    {
        $reflection = ContainerResolver::reflectCallable('DateTime::createFromFormat');

        self::assertInstanceOf(ReflectionMethod::class, $reflection);
        self::assertSame('createFromFormat', $reflection->getName());
    }

    #[Test]
    public function itShouldExpandVariadicArguments(): void
    {
        $service = new SampleService();
        $resolver = new ContainerResolver(new ArrayContainer([SampleService::class => $service]));

        $args = $resolver->resolve($this->constructorOf(VariadicConsumer::class), [1, 2, 3]);

        self::assertSame([$service, 1, 2, 3], $args);
    }

    #[Test]
    public function itShouldSupplyVariadicElementByName(): void
    {
        $service = new SampleService();
        $resolver = new ContainerResolver(new ArrayContainer([SampleService::class => $service]));

        $args = $resolver->resolve($this->constructorOf(VariadicConsumer::class), ['numbers' => 9]);
        $consumer = (new ReflectionClass(VariadicConsumer::class))->newInstanceArgs($args);

        self::assertSame([$service, 9], $args);
        self::assertSame([9], $consumer->numbers);
    }

    #[Test]
    public function itShouldResolveArgumentsReadyToSplatIntoVariadicConstructor(): void
    {
        $service = new SampleService();
        $resolver = new ContainerResolver(new ArrayContainer([SampleService::class => $service]));

        $args = $resolver->resolve($this->constructorOf(VariadicConsumer::class), [1, 2, 3]);
        $consumer = (new ReflectionClass(VariadicConsumer::class))->newInstanceArgs($args);

        self::assertSame($service, $consumer->service);
        self::assertSame([1, 2, 3], $consumer->numbers);
    }

    #[Test]
    public function itShouldResolveNamedArgumentsReadyToSplat(): void
    {
        $service = new SampleService();
        $resolver = new ContainerResolver(new ArrayContainer([SampleService::class => $service]));

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
