<?php

/*
 * SPDX-License-Identifier: ISC
 * SPDX-FileCopyrightText: (c) Respect Project Contributors
 * SPDX-FileContributor: Alexandre Gomes Gaigalas <alganet@gmail.com>
 */

declare(strict_types=1);

namespace Respect\Parameter;

use Closure;
use Psr\Container\ContainerInterface;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;

use function array_key_exists;
use function array_values;
use function assert;
use function count;
use function is_a;
use function is_array;
use function is_int;
use function is_object;
use function is_string;
use function md5;
use function str_contains;

/**
 * Resolves the arguments to call a function or constructor with, autowiring any parameter that is
 * not supplied from a PSR-11 container by type.
 *
 * The result is always an ordered list ready to splat (`...$args` / `newInstanceArgs`), with
 * variadic parameters expanded.
 */
final readonly class Resolver implements ParameterResolver
{
    private const string CACHE_KEY_PREFIX = 'respect.parameter.spec.';

    public function __construct(
        private ContainerInterface $container,
        private CacheInterface $specCache = new InMemoryCache(),
    ) {
    }

    /**
     * Resolve the arguments for a function/constructor.
     *
     * Provided arguments may be positional (int-keyed) or named (string-keyed by parameter name).
     * For each parameter, in order: an explicit named argument wins; then a positional argument
     * already matching the parameter type; then the container by type; then the next positional
     * argument; then the parameter default; otherwise null. A trailing variadic parameter receives
     * a matching named argument (if any) followed by every remaining positional argument.
     *
     * @param array<int|string, mixed> $arguments
     *
     * @return list<mixed>
     */
    public function resolve(ReflectionFunctionAbstract $reflection, array $arguments): array
    {
        $spec = $this->specFor($reflection);
        if ($spec === []) {
            return array_values($arguments);
        }

        $positional = [];
        $named = [];
        foreach ($arguments as $key => $value) {
            if (is_int($key)) {
                $positional[] = $value;
            } else {
                $named[$key] = $value;
            }
        }

        if (
            $named === []
            && count($positional) === count($spec)
            && self::allPositionalsAlign($spec, $positional, $this->container)
        ) {
            return $positional;
        }

        $resolved = [];
        $posIndex = 0;
        $count = count($positional);

        foreach ($spec as $paramIndex => $param) {
            $name = $param['name'];

            if ($param['variadic']) {
                if (array_key_exists($name, $named)) {
                    $resolved[] = $named[$name];
                }

                while ($posIndex < $count) {
                    $resolved[] = $positional[$posIndex++];
                }

                break;
            }

            if (array_key_exists($name, $named)) {
                $resolved[] = $named[$name];

                continue;
            }

            $type = $param['type'];

            if ($type !== null && isset($positional[$posIndex]) && $positional[$posIndex] instanceof $type) {
                $resolved[] = $positional[$posIndex++];

                continue;
            }

            if ($type !== null && $this->container->has($type)) {
                $resolved[] = $this->container->get($type);

                continue;
            }

            if ($posIndex < $count) {
                $resolved[] = $positional[$posIndex++];
            } elseif ($param['hasDefault']) {
                $resolved[] = $reflection->getParameters()[$paramIndex]->getDefaultValue();
            } else {
                $resolved[] = null;
            }
        }

        return $resolved;
    }

    /**
     * Resolve arguments, with named arguments taking precedence over the container.
     *
     * @deprecated Use {@see resolve()} instead; it now handles named arguments directly.
     *
     * @param array<int|string, mixed> $arguments
     *
     * @return list<mixed>
     */
    public function resolveNamed(ReflectionFunctionAbstract $reflection, array $arguments): array
    {
        return $this->resolve($reflection, $arguments);
    }

    /** Reflect any callable into its ReflectionFunctionAbstract. */
    public static function reflectCallable(callable $callable): ReflectionFunctionAbstract
    {
        if ($callable instanceof Closure) {
            return new ReflectionFunction($callable);
        }

        if (is_array($callable)) {
            /** @var array{object|class-string, string} $callable */ // phpcs:ignore SlevomatCodingStandard.Commenting.InlineDocCommentDeclaration.MissingVariable

            return new ReflectionMethod(...$callable);
        }

        if (is_object($callable)) {
            return new ReflectionMethod($callable, '__invoke');
        }

        if (is_string($callable) && str_contains($callable, '::')) {
            return ReflectionMethod::createFromMethodName($callable);
        }

        assert(is_string($callable));

        return new ReflectionFunction($callable);
    }

    /** @param class-string $type */
    public static function acceptsType(ReflectionFunctionAbstract $reflection, string $type): bool
    {
        foreach ($reflection->getParameters() as $param) {
            $typeName = self::typeName($param);

            if ($typeName !== null && is_a($typeName, $type, true)) {
                return true;
            }
        }

        return false;
    }

    /** @return class-string|null */
    private static function typeName(ReflectionParameter $param): string|null
    {
        $type = $param->getType();

        /** @phpstan-ignore return.type */
        return $type instanceof ReflectionNamedType && !$type->isBuiltin() ? $type->getName() : null;
        /* Ignore Reason: !isBuiltin() guarantees class-string */
    }

    /** @return list<array{name: string, type: class-string|null, variadic: bool, hasDefault: bool}> */
    private function specFor(ReflectionFunctionAbstract $reflection): array
    {
        $key = self::CACHE_KEY_PREFIX . md5($this->cacheKey($reflection));

        try {
            /** @var list<array{name: string, type: class-string|null, variadic: bool, hasDefault: bool}> $cached */
            $cached = $this->specCache->get($key);
        } catch (InvalidArgumentException) {
            $cached = null;
        }

        if (is_array($cached)) {
            return $cached;
        }

        $spec = $this->buildSpec($reflection);

        try {
            $this->specCache->set($key, $spec);
        } catch (InvalidArgumentException) {
        }

        return $spec;
    }

    /** @return non-empty-string */
    private function cacheKey(ReflectionFunctionAbstract $reflection): string
    {
        if ($reflection instanceof ReflectionMethod) {
            return $reflection->getDeclaringClass()->getName() . '::' . $reflection->getName();
        }

        if ($reflection instanceof ReflectionFunction && $reflection->isClosure()) {
            return $reflection->getFileName() . '::' . $reflection->getStartLine();
        }

        return $reflection->getName();
    }

    /** @return list<array{name: string, type: class-string|null, variadic: bool, hasDefault: bool}> */
    private function buildSpec(ReflectionFunctionAbstract $reflection): array
    {
        $spec = [];
        foreach ($reflection->getParameters() as $param) {
            $spec[] = [
                'name' => $param->getName(),
                'type' => self::typeName($param),
                'variadic' => $param->isVariadic(),
                'hasDefault' => $param->isDefaultValueAvailable(),
            ];
        }

        return $spec;
    }

    /**
     * @param list<array{name: string, type: class-string|null, variadic: bool, hasDefault: bool}> $spec
     * @param list<mixed> $positional
     */
    private static function allPositionalsAlign(array $spec, array $positional, ContainerInterface $container): bool
    {
        foreach ($spec as $i => $param) {
            if ($param['variadic']) {
                // A variadic consumes "the rest"; the count precondition
                // (count($positional) === count($spec)) would leave exactly
                // one positional for it, which is technically safe here, but
                // bailing keeps the proof simple and the cost is negligible.
                return false;
            }

            $type = $param['type'];

            if ($type === null) {
                continue;
            }

            if (isset($positional[$i]) && $positional[$i] instanceof $type) {
                continue;
            }

            if ($container->has($type)) {
                return false;
            }
        }

        return true;
    }
}
