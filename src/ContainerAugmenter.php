<?php

/*
 * SPDX-License-Identifier: ISC
 * SPDX-FileCopyrightText: (c) Respect Project Contributors
 * SPDX-FileContributor: Henrique Moody <henriquemoody@gmail.com>
 */

declare(strict_types=1);

namespace Respect\Parameter;

use Psr\Container\ContainerInterface;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionNamedType;

use function array_filter;
use function array_is_list;
use function array_key_exists;
use function array_keys;
use function class_exists;
use function count;
use function in_array;
use function interface_exists;
use function is_int;

/**
 * Augments arguments with services from a PSR-11 container.
 *
 * Types listed as unresolvable are never looked up in the container, which
 * keeps value-like classes (clocks, dates) from being served as services.
 */
final class ContainerAugmenter implements Augmenter
{
    /** @var array<string, list<array{int, string, class-string}>> */
    private array $augmentableParametersCache = [];

    /** @param array<class-string> $unresolvableTypes */
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly array $unresolvableTypes = [],
    ) {
    }

    /**
     * @param array<int|string, mixed> $arguments Positional and/or named arguments
     *
     * @return array<int|string, mixed>
     */
    public function augment(ReflectionFunctionAbstract $reflection, array $arguments): array
    {
        if (count($arguments) >= $reflection->getNumberOfParameters()) {
            return $arguments;
        }

        $augmentableParameters = $this->augmentableParameters($reflection);
        if ($augmentableParameters === []) {
            return $arguments;
        }

        $positionalArgumentsCount = count(
            array_is_list($arguments) ? $arguments : array_filter(array_keys($arguments), is_int(...)),
        );

        foreach ($augmentableParameters as [$position, $name, $type]) {
            if ($position < $positionalArgumentsCount || array_key_exists($name, $arguments)) {
                continue;
            }

            if (!$this->container->has($type)) {
                continue;
            }

            $arguments[$name] = $this->container->get($type);
        }

        return $arguments;
    }

    /** @return list<array{int, string, class-string}> */
    private function augmentableParameters(ReflectionFunctionAbstract $reflection): array
    {
        $cacheKey = self::createCacheKey($reflection);
        if (isset($this->augmentableParametersCache[$cacheKey])) {
            return $this->augmentableParametersCache[$cacheKey];
        }

        $parameters = [];
        foreach ($reflection->getParameters() as $parameter) {
            $type = $parameter->getType();
            if ($parameter->isVariadic() || !$type instanceof ReflectionNamedType || $type->isBuiltin()) {
                continue;
            }

            $typeName = $type->getName();
            if (!class_exists($typeName) && !interface_exists($typeName)) {
                continue;
            }

            if (in_array($typeName, $this->unresolvableTypes, true)) {
                continue;
            }

            $parameters[] = [$parameter->getPosition(), $parameter->getName(), $typeName];
        }

        return $this->augmentableParametersCache[$cacheKey] = $parameters;
    }

    private static function createCacheKey(ReflectionFunctionAbstract $reflection): string
    {
        if ($reflection instanceof ReflectionMethod) {
            return $reflection->class . '::' . $reflection->name;
        }

        if (!$reflection->isClosure()) {
            return $reflection->name;
        }

        $file = $reflection->getFileName() ?: 'internal';
        $line = $reflection->getStartLine() ?: 0;

        return $reflection->getName() . '@' . $file . ':' . $line;
    }
}
