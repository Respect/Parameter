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
use function str_contains;

/**
 * Resolves the arguments to call a function or constructor with, autowiring any parameter that is
 * not supplied from a PSR-11 container by type.
 *
 * The result is always an ordered list ready to splat (`...$args` / `newInstanceArgs`), with
 * variadic parameters expanded.
 */
final readonly class ContainerResolver implements Resolver
{
    public function __construct(private ContainerInterface $container)
    {
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
        $parameters = $reflection->getParameters();
        if ($parameters === []) {
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

        $resolved = [];
        $index = 0;
        $count = count($positional);

        foreach ($parameters as $param) {
            $name = $param->getName();

            if ($param->isVariadic()) {
                if (array_key_exists($name, $named)) {
                    $resolved[] = $named[$name];
                }

                while ($index < $count) {
                    $resolved[] = $positional[$index++];
                }

                break;
            }

            if (array_key_exists($name, $named)) {
                $resolved[] = $named[$name];

                continue;
            }

            $type = self::typeName($param);

            if ($type !== null && isset($positional[$index]) && $positional[$index] instanceof $type) {
                $resolved[] = $positional[$index++];

                continue;
            }

            if ($type !== null && $this->container->has($type)) {
                $resolved[] = $this->container->get($type);

                continue;
            }

            if ($index < $count) {
                $resolved[] = $positional[$index++];
            } elseif ($param->isDefaultValueAvailable()) {
                $resolved[] = $param->getDefaultValue();
            } else {
                $resolved[] = null;
            }
        }

        return $resolved;
    }

    /** Reflect any callable into its ReflectionFunctionAbstract. */
    public static function reflectCallable(callable $callable): ReflectionFunctionAbstract
    {
        if ($callable instanceof Closure) {
            return new ReflectionFunction($callable);
        }

        if (is_array($callable)) {
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
}
