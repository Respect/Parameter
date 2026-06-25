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
use Respect\Parameter\InMemoryCache;
use Respect\Parameter\ParameterResolver;
use Respect\Parameter\Resolver;
use Respect\Parameter\Test\Fixtures\ArrayCache;
use Respect\Parameter\Test\Fixtures\ArrayContainer;
use Respect\Parameter\Test\Fixtures\ConsumerWithExpensiveDefault;
use Respect\Parameter\Test\Fixtures\ExpensiveDefaultService;
use Respect\Parameter\Test\Fixtures\SampleService;
use Respect\Parameter\Test\Fixtures\ServiceConsumer;
use Respect\Parameter\Test\Fixtures\TwoRequiredConsumer;
use Respect\Parameter\Test\Fixtures\VariadicConsumer;

use function md5;

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

    #[Test]
    public function itShouldInjectFromContainerWhenPositionalDoesNotMatchEvenWithCountMatch(): void
    {
        $service = new SampleService();
        $resolver = new Resolver(new ArrayContainer([SampleService::class => $service]));

        // count(args) === count(params) === 2, all params required, no variadic.
        // The naive count-only fast path would return ['hello', 'world'] here,
        // but the resolver must inject $service from the container and shift
        // 'hello' onto $value (dropping 'world').
        $args = $resolver->resolve($this->constructorOf(TwoRequiredConsumer::class), ['hello', 'world']);

        self::assertSame([$service, 'hello'], $args);
    }

    #[Test]
    public function itShouldPassThroughWhenAllPositionalsAlignWithContainerResolvableType(): void
    {
        $service = new SampleService();
        $resolver = new Resolver(new ArrayContainer([SampleService::class => $service]));

        // Same shape as the previous test, but the positional already matches
        // the parameter type — the sound fast path fires and returns the
        // arguments unchanged, skipping introspection and container lookup.
        $args = $resolver->resolve($this->constructorOf(TwoRequiredConsumer::class), [$service, 'hello']);

        self::assertSame([$service, 'hello'], $args);
    }

    #[Test]
    public function itShouldShareSpecAcrossDistinctReflectionsOfSameCallable(): void
    {
        $cache = new ArrayCache();
        $resolver = new Resolver(
            new ArrayContainer([SampleService::class => new SampleService()]),
            $cache,
        );

        // Two brand-new ReflectionMethod instances of the same constructor.
        // Without a stable cache key, each would build and store its own spec
        // (WeakMap keyed on object identity misses here). With the PSR-16
        // cache keyed on FQCN::method, the second resolve() must hit.
        $r1 = $this->constructorOf(ServiceConsumer::class);
        $r2 = $this->constructorOf(ServiceConsumer::class);

        self::assertNotSame($r1, $r2);

        $resolver->resolve($r1, ['hi']);
        $resolver->resolve($r2, ['hi']);

        self::assertSame(1, $cache->sets, 'spec built and stored exactly once');
        self::assertSame(1, $cache->hits, 'second resolve() served from cache');
        self::assertSame(1, $cache->misses, 'first resolve() missed the cache');
    }

    #[Test]
    public function itShouldBypassCacheWhenNoCacheSupplied(): void
    {
        // Default ctor: no cache. Resolver still works; spec is rebuilt per call.
        $resolver = new Resolver(new ArrayContainer([SampleService::class => new SampleService()]));

        $args1 = $resolver->resolve($this->constructorOf(ServiceConsumer::class), ['hi']);
        $args2 = $resolver->resolve($this->constructorOf(ServiceConsumer::class), ['hi']);

        self::assertSame($args1, $args2);
    }

    #[Test]
    public function itShouldMemoizeSpecAcrossReflectionsUsingBundledInMemoryCache(): void
    {
        // The bundled InMemoryCache (no external dependency) must work as a
        // real spec cache: same callable, two fresh ReflectionMethod instances,
        // spec is built once and served from cache on the second call.
        $cache = new InMemoryCache();
        $resolver = new Resolver(
            new ArrayContainer([SampleService::class => new SampleService()]),
            $cache,
        );

        $r1 = $this->constructorOf(ServiceConsumer::class);
        $r2 = $this->constructorOf(ServiceConsumer::class);
        self::assertNotSame($r1, $r2);

        $args1 = $resolver->resolve($r1, ['hi']);
        $args2 = $resolver->resolve($r2, ['hi']);

        self::assertSame($args1, $args2);
        // Second call must be a cache hit: spec key present in the bundled cache.
        self::assertTrue(
            $cache->has(
                'respect.parameter.spec.' . md5('Respect\Parameter\Test\Fixtures\ServiceConsumer::__construct'),
            ),
        );
    }

    #[Test]
    public function itShouldNotEagerlyConstructObjectDefaultsWhenSpecIsBuilt(): void
    {
        // PHP 8.1+ `new ExpensiveDefaultService()` as a parameter default must
        // NOT run while the spec is built or cached — only when the default
        // branch is actually taken in resolve(). Here we supply both positionals
        // so the default branch never fires; if buildSpec() eagerly evaluated
        // the default (the old behavior), an instance would still be constructed
        // during spec build/cache and this test would fail.
        ExpensiveDefaultService::$instances = 0;
        $cache = new ArrayCache();
        $resolver = new Resolver(new ArrayContainer(), $cache);
        $explicit = new ExpensiveDefaultService();
        $pre = ExpensiveDefaultService::$instances;

        $resolver->resolve(
            $this->constructorOf(ConsumerWithExpensiveDefault::class),
            ['hi', $explicit],
        );

        self::assertSame(
            $pre,
            ExpensiveDefaultService::$instances,
            'object default not eagerly constructed during spec build/cache',
        );
        self::assertSame(1, $cache->sets, 'spec was built and stored once');
    }

    #[Test]
    public function itShouldConstructObjectDefaultLazilyOnlyWhenDefaultBranchIsTaken(): void
    {
        ExpensiveDefaultService::$instances = 0;
        $resolver = new Resolver(new ArrayContainer(), new ArrayCache());

        // Supply no $service → the default branch fires → the object default
        // is constructed exactly once for this call.
        $args = $resolver->resolve($this->constructorOf(ConsumerWithExpensiveDefault::class), ['hi']);

        self::assertSame(
            1,
            ExpensiveDefaultService::$instances,
            'object default constructed exactly once when default branch taken',
        );
        self::assertInstanceOf(ExpensiveDefaultService::class, $args[1]);
    }

    /** @param class-string $class */
    private function constructorOf(string $class): ReflectionMethod
    {
        $constructor = (new ReflectionClass($class))->getConstructor();
        self::assertNotNull($constructor);

        return $constructor;
    }
}
