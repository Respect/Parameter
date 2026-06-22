<?php

/*
 * SPDX-License-Identifier: ISC
 * SPDX-FileCopyrightText: (c) Respect Project Contributors
 * SPDX-FileContributor: Henrique Moody <henriquemoody@gmail.com>
 */

declare(strict_types=1);

namespace Respect\Parameter\Test\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use Respect\Parameter\ContainerAugmenter;
use Respect\Parameter\Test\Fixtures\ArrayContainer;
use Respect\Parameter\Test\Fixtures\OptionalServiceConsumer;
use Respect\Parameter\Test\Fixtures\SampleService;

use function Respect\Parameter\Test\Fixtures\namedFunctionWithService;

#[CoversClass(ContainerAugmenter::class)]
final class ContainerAugmenterTest extends TestCase
{
    #[Test]
    public function itShouldAugmentArgumentsWithContainerValuesForUnfilledParameters(): void
    {
        $service = new SampleService();
        $augmenter = new ContainerAugmenter(new ArrayContainer([SampleService::class => $service]));

        self::assertSame(
            ['some name', 'service' => $service],
            $augmenter->augment($this->constructorOf(OptionalServiceConsumer::class), ['some name']),
        );
    }

    #[Test]
    public function itShouldNotAugmentWhenPositionalArgumentsFillAllParameters(): void
    {
        $augmenter = new ContainerAugmenter(new ArrayContainer([SampleService::class => new SampleService()]));
        $arguments = ['some name', new SampleService()];

        self::assertSame(
            $arguments,
            $augmenter->augment($this->constructorOf(OptionalServiceConsumer::class), $arguments),
        );
    }

    #[Test]
    public function itShouldNotAugmentWhenNamedArgumentsFillAugmentableParameters(): void
    {
        $augmenter = new ContainerAugmenter(new ArrayContainer([SampleService::class => new SampleService()]));
        $arguments = ['service' => new SampleService()];

        self::assertSame(
            $arguments,
            $augmenter->augment($this->constructorOf(OptionalServiceConsumer::class), $arguments),
        );
    }

    #[Test]
    public function itShouldNotAugmentWhenContainerDoesNotHaveParameterType(): void
    {
        $augmenter = new ContainerAugmenter(new ArrayContainer());

        self::assertSame([], $augmenter->augment($this->constructorOf(OptionalServiceConsumer::class), []));
    }

    #[Test]
    public function itShouldNotAugmentUnresolvableTypes(): void
    {
        $service = new SampleService();
        $augmenter = new ContainerAugmenter(
            new ArrayContainer([SampleService::class => $service]),
            [SampleService::class],
        );

        self::assertSame(
            ['some name'],
            $augmenter->augment($this->constructorOf(OptionalServiceConsumer::class), ['some name']),
        );
    }

    #[Test]
    public function itShouldNotAugmentVariadicParameters(): void
    {
        $service = new SampleService();
        $augmenter = new ContainerAugmenter(new ArrayContainer([SampleService::class => $service]));

        $closure = static fn(string $name, SampleService ...$services): bool => true;

        self::assertSame([], $augmenter->augment(new ReflectionFunction($closure), []));
    }

    #[Test]
    public function itShouldNotAugmentBuiltinTypes(): void
    {
        $augmenter = new ContainerAugmenter(new ArrayContainer(['string' => 'value']));

        $closure = static fn(string $name, int $count): bool => true;

        self::assertSame([], $augmenter->augment(new ReflectionFunction($closure), []));
    }

    #[Test]
    public function itShouldNotAugmentParametersWithNonExistentType(): void
    {
        $augmenter = new ContainerAugmenter(new ArrayContainer());

        $function = new ReflectionFunction('Respect\Parameter\Test\Fixtures\functionWithNonExistentType');

        self::assertSame([], $augmenter->augment($function, []));
    }

    #[Test]
    public function itShouldAugmentArgumentsForClosure(): void
    {
        $service = new SampleService();
        $augmenter = new ContainerAugmenter(new ArrayContainer([SampleService::class => $service]));

        $closure = static fn(string $name, SampleService $service): bool => true;

        self::assertSame(
            ['some name', 'service' => $service],
            $augmenter->augment(new ReflectionFunction($closure), ['some name']),
        );
    }

    #[Test]
    public function itShouldKeepNamedArgumentsWhenAugmenting(): void
    {
        $service = new SampleService();
        $augmenter = new ContainerAugmenter(new ArrayContainer([SampleService::class => $service]));

        $closure = static fn(string $name, SampleService $service): bool => true;

        self::assertSame(
            ['name' => 'some name', 'service' => $service],
            $augmenter->augment(new ReflectionFunction($closure), ['name' => 'some name']),
        );
    }

    #[Test]
    public function itShouldAugmentArgumentsForNamedFunction(): void
    {
        $service = new SampleService();
        $augmenter = new ContainerAugmenter(new ArrayContainer([SampleService::class => $service]));

        $function = new ReflectionFunction(namedFunctionWithService(...));

        self::assertSame(
            ['some name', 'service' => $service],
            $augmenter->augment($function, ['some name']),
        );
    }

    #[Test]
    public function itShouldCreateCacheKeyForNamedFunction(): void
    {
        $service = new SampleService();
        $augmenter = new ContainerAugmenter(new ArrayContainer([SampleService::class => $service]));

        $function = new ReflectionFunction('Respect\Parameter\Test\Fixtures\namedFunctionWithService');

        self::assertSame(
            ['some name', 'service' => $service],
            $augmenter->augment($function, ['some name']),
        );
    }

    #[Test]
    public function itShouldUseCachedAugmentableParametersOnSubsequentCalls(): void
    {
        $service = new SampleService();
        $augmenter = new ContainerAugmenter(new ArrayContainer([SampleService::class => $service]));

        $constructor = $this->constructorOf(OptionalServiceConsumer::class);

        $augmenter->augment($constructor, ['some name']);

        self::assertSame(
            ['another name', 'service' => $service],
            $augmenter->augment($constructor, ['another name']),
        );
    }

    #[Test]
    public function itShouldNotAugmentDateTimeTypesWhenListedAsUnresolvable(): void
    {
        $now = new DateTimeImmutable();
        $augmenter = new ContainerAugmenter(
            new ArrayContainer([DateTimeImmutable::class => $now]),
            [DateTimeImmutable::class],
        );

        $closure = static fn(DateTimeImmutable $date): bool => true;

        self::assertSame([], $augmenter->augment(new ReflectionFunction($closure), []));
    }

    /** @param class-string $class */
    private function constructorOf(string $class): ReflectionMethod
    {
        $constructor = (new ReflectionClass($class))->getConstructor();
        self::assertNotNull($constructor);

        return $constructor;
    }
}
