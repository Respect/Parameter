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
use function array_key_last;
use function array_keys;
use function array_pop;
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
     * For each parameter, in order: an explicit named argument wins; then any not-yet-consumed
     * positional argument matching the parameter type (regardless of its position); then the
     * container by type; then the next not-yet-consumed positional argument; then the parameter
     * default; otherwise null. A trailing variadic parameter receives a matching named argument
     * (if any) followed by every remaining positional argument.
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

        // A variadic parameter is always the trailing one in PHP, so pull it off once here
        // instead of testing isVariadic() on every parameter inside the passes below.
        $variadic = null;
        $lastKey = array_key_last($parameters);
        if ($parameters[$lastKey]->isVariadic()) {
            $variadic = $parameters[$lastKey];
            array_pop($parameters);
        }

        $slot = [];
        $used = [];
        $deferred = [];

        // First pass: bind named arguments and typed parameters. A typed parameter claims any
        // matching positional argument regardless of position, so a leading scalar parameter can
        // never steal an object meant for a typed parameter declared after it.
        foreach ($parameters as $index => $param) {
            $name = $param->getName();
            if (array_key_exists($name, $named)) {
                $slot[$index] = $named[$name];

                continue;
            }

            $type = self::typeName($param);
            if ($type !== null) {
                $match = self::firstUnused($positional, $used, $type);
                if ($match !== null) {
                    $slot[$index] = $positional[$match];
                    $used[$match] = true;

                    continue;
                }

                if ($this->container->has($type)) {
                    $slot[$index] = $this->container->get($type);

                    continue;
                }
            }

            $deferred[$index] = $param;
        }

        // Second pass: fill the remaining parameters from leftover positional arguments in order,
        // advancing a single cursor instead of rescanning from the start for each one.
        $cursor = 0;
        $total = count($positional);
        foreach ($deferred as $index => $param) {
            while ($cursor < $total && ($used[$cursor] ?? false)) {
                $cursor++;
            }

            if ($cursor < $total) {
                $slot[$index] = $positional[$cursor];
                $used[$cursor] = true;
                $cursor++;
            } elseif ($param->isDefaultValueAvailable()) {
                $slot[$index] = $param->getDefaultValue();
            } else {
                $slot[$index] = null;
            }
        }

        // Assemble the fixed parameters in declaration order (plain array reads, no reflection),
        // then expand a trailing variadic from whatever named element and positional arguments
        // remain unconsumed.
        $resolved = [];
        foreach (array_keys($parameters) as $index) {
            $resolved[] = $slot[$index];
        }

        if ($variadic !== null) {
            $name = $variadic->getName();
            if (array_key_exists($name, $named)) {
                $resolved[] = $named[$name];
            }

            foreach ($positional as $i => $value) {
                if ($used[$i] ?? false) {
                    continue;
                }

                $resolved[] = $value;
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

    /**
     * Index of the first positional argument not yet consumed, optionally constrained to one whose
     * value is an instance of the given type. Returns null when no such argument remains.
     *
     * @param list<mixed>      $positional
     * @param array<int, bool> $used
     * @param class-string|null $type
     */
    private static function firstUnused(array $positional, array $used, string|null $type): int|null
    {
        foreach ($positional as $i => $value) {
            if ($used[$i] ?? false) {
                continue;
            }

            if ($type === null || $value instanceof $type) {
                return $i;
            }
        }

        return null;
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
