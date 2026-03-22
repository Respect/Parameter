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
use function is_object;
use function is_string;
use function str_contains;

/**
 * Resolves function/constructor parameters from PSR-11 containers.
 *
 * For each parameter, tries by type (non-builtin) then by name against each
 * container in order. Falls through to positional arguments, then defaults.
 */
final readonly class Resolver
{
    /** @var array<int, ContainerInterface> */
    private array $containers;

    public function __construct(ContainerInterface ...$containers)
    {
        $this->containers = array_values($containers);
    }

    /**
     * Resolve parameters for a function/constructor from positional arguments.
     *
     * @param array<int, mixed> $arguments User-provided positional arguments
     *
     * @return array<int, mixed> Resolved arguments
     */
    public function resolve(ReflectionFunctionAbstract $reflection, array $arguments): array
    {
        $params = $reflection->getParameters();
        if ($params === []) {
            return $arguments;
        }

        $resolvedArgs = [];
        $argIndex = 0;
        $argCount = count($arguments);

        foreach ($params as $param) {
            $typeName = self::typeName($param);

            // User override: positional arg of matching type beats container
            if ($typeName !== null && isset($arguments[$argIndex]) && $arguments[$argIndex] instanceof $typeName) {
                $resolvedArgs[] = $arguments[$argIndex++];

                continue;
            }

            [$found, $value] = $this->fromContainers($param->getName(), $typeName);
            if ($found) {
                $resolvedArgs[] = $value;

                continue;
            }

            if ($argIndex < $argCount) {
                $resolvedArgs[] = $arguments[$argIndex++];
            } elseif ($param->isDefaultValueAvailable()) {
                $resolvedArgs[] = $param->getDefaultValue();
            } else {
                $resolvedArgs[] = null;
            }
        }

        return $resolvedArgs;
    }

    /**
     * Resolve parameters from explicit named args + containers.
     * Named args take precedence over container values.
     *
     * @param array<string, mixed> $namedArgs
     *
     * @return array<int, mixed> Resolved arguments
     */
    public function resolveNamed(ReflectionFunctionAbstract $reflection, array $namedArgs): array
    {
        $params = $reflection->getParameters();
        if ($params === []) {
            return [];
        }

        $resolvedArgs = [];

        foreach ($params as $param) {
            $paramName = $param->getName();

            if (array_key_exists($paramName, $namedArgs)) {
                $resolvedArgs[] = $namedArgs[$paramName];

                continue;
            }

            [$found, $value] = $this->fromContainers($paramName, self::typeName($param));
            if ($found) {
                $resolvedArgs[] = $value;

                continue;
            }

            $resolvedArgs[] = $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null;
        }

        return $resolvedArgs;
    }

    /**
     * Convert positional arguments to a name-keyed map using reflection param names.
     *
     * @param array<int, mixed> $positional
     *
     * @return array<string, mixed>
     */
    public static function toNamedArgs(ReflectionFunctionAbstract $reflection, array $positional): array
    {
        $named = [];
        foreach ($reflection->getParameters() as $param) {
            $position = $param->getPosition();
            if (!isset($positional[$position])) {
                continue;
            }

            $named[$param->getName()] = $positional[$position];
        }

        return $named;
    }

    /** Reflect any callable into its ReflectionFunctionAbstract. */
    public static function reflectCallable(callable $callable): ReflectionFunctionAbstract
    {
        if ($callable instanceof Closure) {
            return new ReflectionFunction($callable);
        }

        if (is_array($callable)) {
            /** @var array{object|string, string} $callable */ // phpcs:ignore SlevomatCodingStandard.Commenting.InlineDocCommentDeclaration.MissingVariable

            return new ReflectionMethod($callable[0], $callable[1]);
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

    /** Check if any parameter of the function accepts a given type. */
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

    private static function typeName(ReflectionParameter $param): string|null
    {
        $type = $param->getType();

        return $type instanceof ReflectionNamedType && !$type->isBuiltin() ? $type->getName() : null;
    }

    /** @return array{bool, mixed} */
    private function fromContainers(string $paramName, string|null $typeName): array
    {
        foreach ($this->containers as $container) {
            if ($typeName !== null && $container->has($typeName)) {
                return [true, $container->get($typeName)];
            }

            if ($container->has($paramName)) {
                return [true, $container->get($paramName)];
            }
        }

        return [false, null];
    }
}
