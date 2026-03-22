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
use function assert;
use function count;
use function is_a;
use function is_array;
use function is_object;
use function is_string;
use function str_contains;

/**
 * Resolves function/constructor parameters from a PSR-11 container.
 *
 * For each parameter, tries by type (non-builtin) against the container.
 * Falls through to positional arguments, then defaults.
 */
final readonly class Resolver
{
    public function __construct(private ContainerInterface $container)
    {
    }

    /**
     * Resolve parameters for a function/constructor from positional arguments.
     *
     * @param array<int, mixed> $arguments User-provided positional arguments
     *
     * @return array<int, mixed>|array<string, mixed> Resolved arguments keyed by parameter name
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
            $paramName = $param->getName();
            $typeName = self::typeName($param);

            if ($typeName !== null && isset($arguments[$argIndex]) && $arguments[$argIndex] instanceof $typeName) {
                $resolvedArgs[$paramName] = $arguments[$argIndex++];

                continue;
            }

            if ($typeName !== null && $this->container->has($typeName)) {
                $resolvedArgs[$paramName] = $this->container->get($typeName);

                continue;
            }

            if ($argIndex < $argCount) {
                $resolvedArgs[$paramName] = $arguments[$argIndex++];
            } elseif ($param->isDefaultValueAvailable()) {
                $resolvedArgs[$paramName] = $param->getDefaultValue();
            } else {
                $resolvedArgs[$paramName] = null;
            }
        }

        return $resolvedArgs;
    }

    /**
     * Resolve parameters from explicit named args + container.
     * Named args take precedence over container values.
     *
     * @param array<string, mixed> $namedArgs
     *
     * @return array<string, mixed> Resolved arguments keyed by parameter name
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
                $resolvedArgs[$paramName] = $namedArgs[$paramName];

                continue;
            }

            $typeName = self::typeName($param);

            if ($typeName !== null && $this->container->has($typeName)) {
                $resolvedArgs[$paramName] = $this->container->get($typeName);

                continue;
            }

            $resolvedArgs[$paramName] = $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null;
        }

        return $resolvedArgs;
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
}
