<?php

/*
 * SPDX-License-Identifier: ISC
 * SPDX-FileCopyrightText: (c) Respect Project Contributors
 */

declare(strict_types=1);

namespace Respect\Parameter\Test\Unit;

use DateInterval;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException as PsrInvalidArgumentException;
use Respect\Parameter\InMemoryCache;
use Respect\Parameter\InvalidCacheKey;
use stdClass;

use function array_key_exists;
use function iterator_to_array;

#[CoversClass(InMemoryCache::class)]
#[CoversClass(InvalidCacheKey::class)]
final class InMemoryCacheTest extends TestCase
{
    #[Test]
    public function itShouldImplementPsr16CacheInterface(): void
    {
        self::assertInstanceOf(
            CacheInterface::class,
            new InMemoryCache(),
        );
    }

    #[Test]
    public function itShouldReturnDefaultWhenKeyIsAbsent(): void
    {
        $cache = new InMemoryCache();

        self::assertSame('fallback', $cache->get('missing', 'fallback'));
    }

    #[Test]
    public function itShouldReturnNullByDefaultWhenNoDefaultGiven(): void
    {
        self::assertNull((new InMemoryCache())->get('missing'));
    }

    #[Test]
    public function itShouldStoreAndRetrieveScalarValues(): void
    {
        $cache = new InMemoryCache();

        $cache->set('answer', 42);

        self::assertSame(42, $cache->get('answer'));
    }

    #[Test]
    public function itShouldStoreAndRetrieveComplexValues(): void
    {
        $cache = new InMemoryCache();
        $object = new stdClass();
        $object->name = 'Alice';

        $cache->set('user', $object);
        $cache->set('list', [1, 2, 3]);
        $cache->set('null', null);

        self::assertSame($object, $cache->get('user'));
        self::assertSame([1, 2, 3], $cache->get('list'));
        self::assertNull($cache->get('null'));
        // Distinguish stored null from absent key via has()
        self::assertTrue($cache->has('null'));
        self::assertFalse($cache->has('absent'));
    }

    #[Test]
    public function itShouldOverwritePreviousValueOnSet(): void
    {
        $cache = new InMemoryCache();

        $cache->set('key', 'first');
        $cache->set('key', 'second');

        self::assertSame('second', $cache->get('key'));
    }

    #[Test]
    public function itShouldReportWhetherKeyExistsViaHas(): void
    {
        $cache = new InMemoryCache();
        $cache->set('present', 'value');

        self::assertTrue($cache->has('present'));
        self::assertFalse($cache->has('absent'));
    }

    #[Test]
    public function itShouldDeleteSingleKey(): void
    {
        $cache = new InMemoryCache();
        $cache->set('keep', 'a');
        $cache->set('drop', 'b');

        $deleted = $cache->delete('drop');

        self::assertTrue($deleted);
        self::assertTrue($cache->has('keep'));
        self::assertFalse($cache->has('drop'));
    }

    #[Test]
    public function itShouldReturnTrueWhenDeletingAbsentKey(): void
    {
        self::assertTrue((new InMemoryCache())->delete('never-set'));
    }

    #[Test]
    public function itShouldClearAllEntries(): void
    {
        $cache = new InMemoryCache();
        $cache->set('one', 1);
        $cache->set('two', 2);

        $cleared = $cache->clear();

        self::assertTrue($cleared);
        self::assertFalse($cache->has('one'));
        self::assertFalse($cache->has('two'));
    }

    #[Test]
    public function itShouldGetMultipleValuesWithDefaultForMissing(): void
    {
        $cache = new InMemoryCache();
        $cache->set('a', 1);
        $cache->set('c', 3);

        $out = $cache->getMultiple(['a', 'b', 'c'], 'missing');

        self::assertSame(['a' => 1, 'b' => 'missing', 'c' => 3], iterator_to_array($out, true));
    }

    #[Test]
    public function itShouldSetMultipleValues(): void
    {
        $cache = new InMemoryCache();

        $result = $cache->setMultiple(['x' => 10, 'y' => 20, 'z' => 30]);

        self::assertTrue($result);
        self::assertSame(10, $cache->get('x'));
        self::assertSame(20, $cache->get('y'));
        self::assertSame(30, $cache->get('z'));
    }

    #[Test]
    public function itShouldDeleteMultipleKeys(): void
    {
        $cache = new InMemoryCache();
        $cache->setMultiple(['a' => 1, 'b' => 2, 'c' => 3]);

        $result = $cache->deleteMultiple(['a', 'c']);

        self::assertTrue($result);
        self::assertFalse($cache->has('a'));
        self::assertTrue($cache->has('b'));
        self::assertFalse($cache->has('c'));
    }

    #[Test]
    public function itShouldAcceptTtlOnSetWithoutExpiring(): void
    {
        // TTL is accepted for PSR-16 conformance but ignored (process-local cache).
        $cache = new InMemoryCache();

        $cache->set('key', 'value', 60);
        $cache->set('key2', 'value2', new DateInterval('PT60S'));

        self::assertSame('value', $cache->get('key'));
        self::assertSame('value2', $cache->get('key2'));
    }

    #[Test]
    public function itShouldThrowWhenSetMultipleReceivesNonStringKey(): void
    {
        $cache = new InMemoryCache();

        $this->expectException(InvalidCacheKey::class);

        // int keys (PHP's natural array iteration) must be rejected as PSR-16 keys.
        /** @phpstan-ignore argument.type (deliberately invalid input for this throw test) */
        $cache->setMultiple([1 => 'a', 2 => 'b']);
    }

    #[Test]
    public function itShouldThrowWhenSetMultipleReceivesEmptyStringKey(): void
    {
        $cache = new InMemoryCache();

        $this->expectException(InvalidCacheKey::class);

        $cache->setMultiple(['' => 'a']);
    }

    #[Test]
    public function itShouldThrowPsr16CompliantInvalidArgumentInstance(): void
    {
        // The concrete InvalidCacheKey must implement Psr\SimpleCache\InvalidArgumentException
        // so callers catching the PSR-16 interface see it.
        $cache = new InMemoryCache();

        try {
            /** @phpstan-ignore argument.type (deliberately invalid input for this throw test) */
            $cache->setMultiple([1 => 'a']);
            self::fail('Expected InvalidCacheKey to be thrown');
        } catch (InvalidCacheKey $e) {
            self::assertInstanceOf(PsrInvalidArgumentException::class, $e);
        }
    }

    #[Test]
    public function itShouldIsolateCacheInstances(): void
    {
        $a = new InMemoryCache();
        $b = new InMemoryCache();

        $a->set('secret', 'one');

        self::assertFalse($b->has('secret'));
        self::assertSame('one', $a->get('secret'));
    }

    #[Test]
    public function itShouldAllowSubclassesToIntrospectTheStore(): void
    {
        // Asserts the protected $store hook is usable from subclasses (test doubles,
        // integrations). The fixture ArrayCache relies on this.
        $double = new class extends InMemoryCache {
            public function hasStoreKey(string $key): bool
            {
                return array_key_exists($key, $this->store);
            }
        };

        $double->set('k', 'v');

        self::assertTrue($double->hasStoreKey('k'));
    }
}
