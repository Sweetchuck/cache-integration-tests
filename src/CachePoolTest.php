<?php

declare(strict_types = 1);

/**
 * This file is part of php-cache organization.
 *
 * (c) 2015-2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm
 * <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\IntegrationTests;

use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;

abstract class CachePoolTest extends TestCase
{

    /**
     * With functionName => reason.
     *
     * @phpstan-var array<string, string>
     */
    protected array $skippedTests = [];

    protected ?CacheItemPoolInterface $cache = null;

    abstract public function createCachePool(): CacheItemPoolInterface;

    /**
     * @before
     */
    public function setupService(): void
    {
        $this->cache = $this->createCachePool();
    }

    /**
     * @after
     */
    public function tearDownService(): void
    {
        $this->cache?->clear();
    }

    /**
     * Data provider for invalid keys.
     */
    public static function invalidKeys(): array
    {
        $cases = [];

        $invalidChars = ['{', '}', '(', ')', '/', '\\', '@', ':'];
        foreach ($invalidChars as $char) {
            $cases += [
                "string forbidden char $char only" => [$char],
                "string forbidden char $char begin" => ["{$char}foo"],
                "string forbidden char $char middle" => ["foo{$char}bar"],
                "string forbidden char $char end" => ["foo{$char}"],
            ];
        }

        return $cases;
    }

    protected function skipIf(string $function): void
    {
        if (isset($this->skippedTests[$function])) {
            static::markTestSkipped($this->skippedTests[$function]);
        }
    }

    public function testBasicUsage(): void
    {
        $this->skipIf(__FUNCTION__);

        $item = $this->cache->getItem('key');
        $item->set('4711');
        $this->cache->save($item);

        $item = $this->cache->getItem('key2');
        $item->set('4712');
        $this->cache->save($item);

        $fooItem = $this->cache->getItem('key');
        static::assertTrue($fooItem->isHit());
        static::assertEquals('4711', $fooItem->get());

        $barItem = $this->cache->getItem('key2');
        static::assertTrue($barItem->isHit());
        static::assertEquals('4712', $barItem->get());

        // Remove 'key' and make sure 'key2' is still there.
        $this->cache->deleteItem('key');
        static::assertFalse($this->cache->getItem('key')->isHit());
        static::assertTrue($this->cache->getItem('key2')->isHit());

        // Remove everything.
        $this->cache->clear();
        static::assertFalse($this->cache->getItem('key')->isHit());
        static::assertFalse($this->cache->getItem('key2')->isHit());
    }

    public function testBasicUsageWithLongKey(): void
    {
        $this->skipIf(__FUNCTION__);

        $pool = $this->createCachePool();

        $key = str_repeat('a', 300);

        $item = $pool->getItem($key);
        static::assertFalse($item->isHit());
        static::assertSame($key, $item->getKey());

        $item->set('value');
        static::assertTrue($pool->save($item));

        $item = $pool->getItem($key);
        static::assertTrue($item->isHit());
        static::assertSame($key, $item->getKey());
        static::assertSame('value', $item->get());

        static::assertTrue($pool->deleteItem($key));

        $item = $pool->getItem($key);
        static::assertFalse($item->isHit());
    }

    public function testItemModifiersReturnsStatic(): void
    {
        $this->skipIf(__FUNCTION__);

        $item = $this->cache->getItem('key');
        static::assertSame($item, $item->set('4711'));
        static::assertSame($item, $item->expiresAfter(2));
        static::assertSame($item, $item->expiresAt(new \DateTime('+2hours')));
    }

    public function testGetItem(): void
    {
        $this->skipIf(__FUNCTION__);

        $item = $this->cache->getItem('key');
        $item->set('value');
        $this->cache->save($item);

        // Get existing item.
        $item = $this->cache->getItem('key');
        static::assertEquals(
            'value',
            $item->get(),
            'A stored item must be returned from cached.',
        );

        static::assertEquals(
            'key',
            $item->getKey(),
            'Cache key can not change.',
        );

        // Get non-existent item.
        $item = $this->cache->getItem('key2');
        static::assertFalse($item->isHit());
        static::assertNull($item->get(), "Item's value must be null when isHit is false.");
    }

    public function testGetItems(): void
    {
        $this->skipIf(__FUNCTION__);

        $keys = ['foo', 'bar', 'baz'];
        $items = $this->cache->getItems($keys);

        $count = 0;

        /** @type CacheItemInterface $item */
        foreach ($items as $i => $item) {
            $item->set($i);
            $this->cache->save($item);

            $count++;
        }

        static::assertSame(3, $count);

        $keys[] = 'biz';
        /** @type CacheItemInterface[] $items */
        $items = $this->cache->getItems($keys);
        $count = 0;
        foreach ($items as $key => $item) {
            $itemKey = $item->getKey();
            static::assertEquals(
                $itemKey,
                $key,
                'Keys must be preserved when fetching multiple items',
            );

            static::assertEquals($key !== 'biz', $item->isHit());
            static::assertContains(
                $key,
                $keys,
                'Cache key can not change.',
            );

            // Remove $key for $keys.
            // @todo Use array_search().
            foreach ($keys as $k => $v) {
                if ($v === $key) {
                    unset($keys[$k]);
                }
            }

            $count++;
        }

        static::assertSame(4, $count);
    }

    public function testGetItemsEmpty(): void
    {
        $this->skipIf(__FUNCTION__);

        $items = $this->cache->getItems([]);
        static::assertTrue(
            is_array($items) || $items instanceof \Traversable,
            'A call to getItems with an empty array must always return an array or \Traversable.'
        );

        $count = 0;
        foreach ($items as $item) {
            $count++;
        }

        static::assertSame(0, $count);
    }

    public function testHasItem(): void
    {
        $this->skipIf(__FUNCTION__);

        $item = $this->cache->getItem('key');
        $item->set('value');
        $this->cache->save($item);

        // Has existing item.
        static::assertTrue($this->cache->hasItem('key'));

        // Has non-existent item.
        static::assertFalse($this->cache->hasItem('key2'));
    }

    public function testClear(): void
    {
        $this->skipIf(__FUNCTION__);

        $item = $this->cache->getItem('key');
        $item->set('value');
        $this->cache->save($item);

        $return = $this->cache->clear();

        static::assertTrue($return, 'clear() must return true if cache was cleared.');
        static::assertFalse(
            $this->cache->getItem('key')->isHit(),
            'No item should be a hit after the cache is cleared.',
        );
        static::assertFalse(
            $this->cache->hasItem('key2'),
            'The cache pool should be empty after it is cleared.',
        );
    }

    public function testClearWithDeferredItems(): void
    {
        $this->skipIf(__FUNCTION__);

        $item = $this->cache->getItem('key');
        $item->set('value');
        $this->cache->saveDeferred($item);

        $this->cache->clear();
        $this->cache->commit();

        static::assertFalse(
            $this->cache->getItem('key')->isHit(),
            'Deferred items must be cleared on clear().',
        );
    }

    public function testDeleteItem(): void
    {
        $this->skipIf(__FUNCTION__);

        $item = $this->cache->getItem('key');
        $item->set('value');
        $this->cache->save($item);

        static::assertTrue($this->cache->deleteItem('key'));

        static::assertFalse(
            $this->cache->getItem('key')->isHit(),
            'A deleted item should not be a hit.',
        );

        static::assertFalse(
            $this->cache->hasItem('key'),
            'A deleted item should not be a in cache.',
        );

        static::assertTrue(
            $this->cache->deleteItem('key2'),
            'Deleting an item that does not exist should return true.',
        );
    }

    public function testDeleteItems(): void
    {
        $this->skipIf(__FUNCTION__);

        $items = $this->cache->getItems(['foo', 'bar', 'baz']);

        /** @var \Psr\Cache\CacheItemInterface $item */
        foreach ($items as $idx => $item) {
            $item->set($idx);
            $this->cache->save($item);
        }

        // All should be a hit but 'biz'
        static::assertTrue($this->cache->getItem('foo')->isHit());
        static::assertTrue($this->cache->getItem('bar')->isHit());
        static::assertTrue($this->cache->getItem('baz')->isHit());
        static::assertFalse($this->cache->getItem('biz')->isHit());

        $return = $this->cache->deleteItems(['foo', 'bar', 'biz']);
        static::assertTrue($return);

        static::assertFalse($this->cache->getItem('foo')->isHit());
        static::assertFalse($this->cache->getItem('bar')->isHit());
        static::assertTrue($this->cache->getItem('baz')->isHit());
        static::assertFalse($this->cache->getItem('biz')->isHit());
    }

    public function testSave(): void
    {
        $this->skipIf(__FUNCTION__);

        $item = $this->cache->getItem('key');
        $item->set('value');
        $return = $this->cache->save($item);

        static::assertTrue($return, 'save() should return true when items are saved.');
        static::assertSame('value', $this->cache->getItem('key')->get());
    }

    public function testSaveExpired(): void
    {
        $this->skipIf(__FUNCTION__);

        $item = $this->cache->getItem('key');
        $item->set('value');
        $item->expiresAt(\DateTime::createFromFormat('U', (string) (time() + 10)));
        $this->cache->save($item);
        $item->expiresAt(\DateTime::createFromFormat('U', (string) (time() - 1)));
        $this->cache->save($item);
        $item = $this->cache->getItem('key');
        static::assertFalse($item->isHit(), 'Cache should not save expired items');
    }

    public function testSaveWithoutExpire(): void
    {
        $this->skipIf(__FUNCTION__);

        $item = $this->cache->getItem('test_ttl_null');
        $item->set('data');
        $this->cache->save($item);

        // Use a new pool instance to ensure that we don't hit any caches
        $pool = $this->createCachePool();
        $item = $pool->getItem('test_ttl_null');

        static::assertTrue($item->isHit(), 'Cache should have retrieved the items');
        static::assertEquals('data', $item->get());
    }

    public function testDeferredSave(): void
    {
        $this->skipIf(__FUNCTION__);

        $item = $this->cache->getItem('key');
        $item->set('4711');
        $return = $this->cache->saveDeferred($item);
        static::assertTrue($return, 'save() should return true when items are saved.');

        $item = $this->cache->getItem('key2');
        $item->set('4712');
        $this->cache->saveDeferred($item);

        // They are not saved yet but should be a hit.
        static::assertTrue(
            $this->cache->hasItem('key'),
            'Deferred items should be considered as a part of the cache even before they are committed',
        );
        static::assertTrue(
            $this->cache->getItem('key')->isHit(),
            'Deferred items should be a hit even before they are committed',
        );
        static::assertTrue($this->cache->getItem('key2')->isHit());

        $this->cache->commit();

        // They should be a hit after the commit as well.
        static::assertTrue($this->cache->getItem('key')->isHit());
        static::assertTrue($this->cache->getItem('key2')->isHit());
    }

    public function testDeferredExpired(): void
    {
        $this->skipIf(__FUNCTION__);

        $item = $this->cache->getItem('key');
        $item->set('4711');
        $item->expiresAt(\DateTime::createFromFormat('U', (string) (time() - 1)));
        $this->cache->saveDeferred($item);

        static::assertFalse($this->cache->hasItem('key'), 'Cache should not have expired deferred item');
        $this->cache->commit();
        $item = $this->cache->getItem('key');
        static::assertFalse($item->isHit(), 'Cache should not save expired items');
    }

    public function testDeleteDeferredItem(): void
    {
        $this->skipIf(__FUNCTION__);

        $item = $this->cache->getItem('key');
        $item->set('4711');
        $this->cache->saveDeferred($item);
        static::assertTrue($this->cache->getItem('key')->isHit());

        $this->cache->deleteItem('key');
        static::assertFalse(
            $this->cache->hasItem('key'),
            'You must be able to delete a deferred item before committed.',
        );
        static::assertFalse(
            $this->cache->getItem('key')->isHit(),
            'You must be able to delete a deferred item before committed.',
        );

        $this->cache->commit();
        static::assertFalse($this->cache->hasItem('key'), 'A deleted item should not reappear after commit.');
        static::assertFalse($this->cache->getItem('key')->isHit(), 'A deleted item should not reappear after commit.');
    }

    public function testDeferredSaveWithoutCommit(): void
    {
        $this->skipIf(__FUNCTION__);

        $this->prepareDeferredSaveWithoutCommit();
        gc_collect_cycles();

        $cache = $this->createCachePool();
        static::assertTrue(
            $cache->getItem('key')->isHit(),
            'A deferred item should automatically be committed on CachePool::__destruct().',
        );
    }

    private function prepareDeferredSaveWithoutCommit(): void
    {
        $cache = $this->cache;
        $this->cache = null;

        $item = $cache->getItem('key');
        $item->set('4711');
        $cache->saveDeferred($item);
    }

    public function testCommit(): void
    {
        $this->skipIf(__FUNCTION__);

        $item = $this->cache->getItem('key');
        $item->set('value');
        $this->cache->saveDeferred($item);
        $return = $this->cache->commit();

        static::assertTrue($return, 'commit() should return true on successful commit.');
        static::assertEquals('value', $this->cache->getItem('key')->get());

        $return = $this->cache->commit();
        static::assertTrue($return, 'commit() should return true even if no items were deferred.');
    }

    /**
     * @medium
     */
    public function testExpiration()
    {
        $this->skipIf(__FUNCTION__);

        $item = $this->cache->getItem('key');
        $item->set('value');
        $item->expiresAfter(2);
        $this->cache->save($item);

        sleep(3);
        $item = $this->cache->getItem('key');
        static::assertFalse($item->isHit());
        static::assertNull($item->get(), "Item's value must be null when isHit() is false.");
    }

    public function testExpiresAt(): void
    {
        $this->skipIf(__FUNCTION__);

        $item = $this->cache->getItem('key');
        $item->set('value');
        $item->expiresAt(new \DateTime('+2hours'));
        $this->cache->save($item);

        $item = $this->cache->getItem('key');
        static::assertTrue($item->isHit());
    }

    public function testExpiresAtWithNull(): void
    {
        $this->skipIf(__FUNCTION__);

        $item = $this->cache->getItem('key');
        $item->set('value');
        $item->expiresAt(null);
        $this->cache->save($item);

        $item = $this->cache->getItem('key');
        static::assertTrue($item->isHit());
    }

    public function testExpiresAfterWithNull(): void
    {
        $this->skipIf(__FUNCTION__);

        $item = $this->cache->getItem('key');
        $item->set('value');
        $item->expiresAfter(null);
        $this->cache->save($item);

        $item = $this->cache->getItem('key');
        static::assertTrue($item->isHit());
    }

    public function testKeyLength(): void
    {
        $this->skipIf(__FUNCTION__);

        $key
            = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_.';
        $item = $this->cache->getItem($key);
        $item->set('value');
        static::assertTrue($this->cache->save($item), 'The implementation does not support a valid cache key');
        static::assertTrue($this->cache->hasItem($key));
    }

    /**
     * @dataProvider invalidKeys
     */
    public function testGetItemInvalidKeys($key): void
    {
        $this->skipIf(__FUNCTION__);

        static::expectException(InvalidArgumentException::class);
        $this->cache->getItem($key);
    }

    /**
     * @dataProvider invalidKeys
     */
    public function testGetItemsInvalidKeys($key): void
    {
        $this->skipIf(__FUNCTION__);

        static::expectException(InvalidArgumentException::class);
        $this->cache->getItems(['key1', $key, 'key2']);
    }

    /**
     * @dataProvider invalidKeys
     */
    public function testHasItemInvalidKeys($key): void
    {
        $this->skipIf(__FUNCTION__);

        static::expectException(InvalidArgumentException::class);
        $this->cache->hasItem($key);
    }

    /**
     * @dataProvider invalidKeys
     */
    public function testDeleteItemInvalidKeys($key): void
    {
        $this->skipIf(__FUNCTION__);

        static::expectException(InvalidArgumentException::class);
        $this->cache->deleteItem($key);
    }

    /**
     * @dataProvider invalidKeys
     */
    public function testDeleteItemsInvalidKeys($key): void
    {
        $this->skipIf(__FUNCTION__);

        static::expectException(InvalidArgumentException::class);
        $this->cache->deleteItems(['key1', $key, 'key2']);
    }

    public function testDataTypeString(): void
    {
        $this->skipIf(__FUNCTION__);

        $item = $this->cache->getItem('key');
        $item->set('5');
        $this->cache->save($item);

        $item = $this->cache->getItem('key');
        static::assertSame(
            '5',
            $item->get(),
            'Wrong data type. If we store a string we must get an string back.',
        );
    }

    public function testDataTypeInteger(): void
    {
        $this->skipIf(__FUNCTION__);

        $item = $this->cache->getItem('key');
        $item->set(5);
        $this->cache->save($item);

        $item = $this->cache->getItem('key');
        static::assertSame(
            5,
            $item->get(),
            'Wrong data type. If we store an int we must get an int back.',
        );
    }

    public function testDataTypeNull(): void
    {
        $this->skipIf(__FUNCTION__);

        $item = $this->cache->getItem('key');
        $item->set(null);
        $this->cache->save($item);

        static::assertTrue(
            $this->cache->hasItem('key'),
            'Null is a perfectly fine cache value. hasItem() should return true when null are stored.'
        );
        $item = $this->cache->getItem('key');
        static::assertNull(
            $item->get(),
            'Wrong data type. If we store null we must get an null back.',
        );
        static::assertTrue(
            $item->isHit(),
            'isHit() should return true when null are stored.',
        );
    }

    public function testDataTypeFloat(): void
    {
        $this->skipIf(__FUNCTION__);

        $float = 1.23456789;
        $item = $this->cache->getItem('key');
        $item->set($float);
        $this->cache->save($item);

        $item = $this->cache->getItem('key');
        static::assertSame(
            $float,
            $item->get(),
            'Wrong data type. If we store float we must get an float back.',
        );
        static::assertTrue($item->isHit(), 'isHit() should return true when float are stored.');
    }

    public function testDataTypeBoolean(): void
    {
        $this->skipIf(__FUNCTION__);

        $item = $this->cache->getItem('key');
        $item->set(true);
        $this->cache->save($item);

        $item = $this->cache->getItem('key');
        static::assertTrue($item->get(), 'Wrong data type. If we store boolean we must get an boolean back.');
        static::assertTrue($item->isHit(), 'isHit() should return true when true are stored.');

        $item->set(false);
        $this->cache->save($item);

        $item = $this->cache->getItem('key');
        static::assertFalse($item->get(), 'Wrong data type. If we store boolean we must get an boolean back.');
        static::assertTrue($item->isHit(), 'isHit() should return true when true are stored.');
    }

    public function testDataTypeArray(): void
    {
        $this->skipIf(__FUNCTION__);

        $array = ['a' => 'foo', 2 => 'bar'];
        $item = $this->cache->getItem('key');
        $item->set($array);
        $this->cache->save($item);

        $item = $this->cache->getItem('key');
        static::assertSame($array, $item->get(), 'Wrong data type. If we store array we must get an array back.');
        static::assertTrue($item->isHit(), 'isHit() should return true when array are stored.');
    }

    public function testDataTypeObject(): void
    {
        $this->skipIf(__FUNCTION__);

        $object = new \stdClass();
        $object->a = 'foo';
        $item = $this->cache->getItem('key');
        $item->set($object);
        $this->cache->save($item);

        $item = $this->cache->getItem('key');
        static::assertTrue(is_object($item->get()), 'Wrong data type. If we store object we must get an object back.');
        static::assertEquals($object, $item->get());
        static::assertTrue($item->isHit(), 'isHit() should return true when object are stored.');
    }

    public function testBinaryData(): void
    {
        $this->skipIf(__FUNCTION__);

        $data = '';
        for ($i = 0; $i < 256; $i++) {
            $data .= chr($i);
        }

        $item = $this->cache->getItem('key');
        $item->set($data);
        $this->cache->save($item);

        $item = $this->cache->getItem('key');
        static::assertSame(
            $data,
            $item->get(),
            'Binary data must survive a round trip.',
        );
    }

    public function testIsHit(): void
    {
        $this->skipIf(__FUNCTION__);

        $item = $this->cache->getItem('key');
        $item->set('value');
        $this->cache->save($item);

        $item = $this->cache->getItem('key');
        static::assertTrue($item->isHit());
    }

    public function testIsHitDeferred(): void
    {
        $this->skipIf(__FUNCTION__);

        $item = $this->cache->getItem('key');
        $item->set('value');
        $this->cache->saveDeferred($item);

        // Test accessing the value before it is committed
        $item = $this->cache->getItem('key');
        static::assertTrue($item->isHit());

        $this->cache->commit();
        $item = $this->cache->getItem('key');
        static::assertTrue($item->isHit());
    }

    public function testSaveDeferredWhenChangingValues(): void
    {
        $this->skipIf(__FUNCTION__);

        $item = $this->cache->getItem('key');
        $item->set('value');
        $this->cache->saveDeferred($item);

        $item = $this->cache->getItem('key');
        $item->set('new value');

        $item = $this->cache->getItem('key');
        static::assertSame(
            'value',
            $item->get(),
            'Items that is put in the deferred queue should not get their values changed',
        );

        $this->cache->commit();
        $item = $this->cache->getItem('key');
        static::assertSame(
            'value',
            $item->get(),
            'Items that is put in the deferred queue should not get their values changed',
        );
    }

    public function testSaveDeferredOverwrite(): void
    {
        $this->skipIf(__FUNCTION__);

        $item = $this->cache->getItem('key');
        $item->set('value');
        $this->cache->saveDeferred($item);

        $item = $this->cache->getItem('key');
        $item->set('new value');
        $this->cache->saveDeferred($item);

        $item = $this->cache->getItem('key');
        static::assertSame('new value', $item->get());

        $this->cache->commit();
        $item = $this->cache->getItem('key');
        static::assertSame('new value', $item->get());
    }

    public function testSavingObject(): void
    {
        $this->skipIf(__FUNCTION__);

        $item = $this->cache->getItem('key');
        $item->set(new \DateTime());
        $this->cache->save($item);

        $item = $this->cache->getItem('key');
        $value = $item->get();
        static::assertInstanceOf(
            \DateTime::class,
            $value,
            'You must be able to store objects in cache.',
        );
    }

    /**
     * @medium
     */
    public function testHasItemReturnsFalseWhenDeferredItemIsExpired(): void
    {
        $this->skipIf(__FUNCTION__);

        $item = $this->cache->getItem('key');
        $item->set('value');
        $item->expiresAfter(2);
        $this->cache->saveDeferred($item);

        sleep(3);
        static::assertFalse($this->cache->hasItem('key'));
    }
}
