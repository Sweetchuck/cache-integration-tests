<?php

declare(strict_types = 1);

/**
 * This file is part of php-cache organization.
 *
 * (c) 2015-2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\IntegrationTests;

use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;

abstract class SimpleCacheTest extends TestCase
{
    /**
     * With functionName => reason.
     *
     * @phpstan-var array<string, string>
     */
    protected array $skippedTests = [];

    protected ?CacheInterface $cache = null;

    protected function skipIf(string $function): void
    {
        if (isset($this->skippedTests[$function])) {
            static::markTestSkipped($this->skippedTests[$function]);
        }
    }

    abstract public function createSimpleCache(): CacheInterface;

    /**
     * Advance time perceived by the cache for the purposes of testing TTL.
     *
     * The default implementation sleeps for the specified duration,
     * but subclasses are encouraged to override this,
     * adjusting a mocked time possibly set up in {@link createSimpleCache()},
     * to speed up the tests.
     */
    public function advanceTime(int $seconds): static
    {
        sleep($seconds);

        return $this;
    }

    /**
     * @before
     */
    public function setupService()
    {
        $this->cache = $this->createSimpleCache();
    }

    /**
     * @after
     */
    public function tearDownService()
    {
        $this->cache?->clear();
    }

    /**
     * Data provider for invalid cache keys.
     */
    public static function invalidKeys(): array
    {
        return array_merge(
            static::invalidArrayKeys(),
            [
                [2],
            ]
        );
    }

    /**
     * Data provider for invalid array keys.
     */
    public static function invalidArrayKeys(): array
    {
        $cases = [
            'bool true' => [true],
            'bool false' => [false],
            'null' => [null],
            'object' => [new \stdClass()],
            'array' => [['array']],
            'string empty' => [''],
        ];

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

    /**
     * Data provider for valid keys.
     */
    public static function validKeys(): array
    {
        return [
            ['AbC19_.'],
            ['1234567890123456789012345678901234567890123456789012345678901234'],
        ];
    }

    /**
     * Data provider for valid data to store.
     */
    public static function validData(): array
    {
        return [
            ['AbC19_.'],
            [4711],
            [47.11],
            [true],
            [null],
            [['key' => 'value']],
            [new \stdClass()],
        ];
    }

    public function testSet(): void
    {
        $this->skipIf(__FUNCTION__);

        $result = $this->cache->set('key', 'value');
        static::assertTrue($result, 'set() must return true if success');
        static::assertEquals('value', $this->cache->get('key'));
    }

    /**
     * @medium
     */
    public function testSetTtl(): void
    {
        $this->skipIf(__FUNCTION__);

        $result = $this->cache->set('key1', 'value', 2);
        static::assertTrue($result, 'set() must return true if success');
        static::assertEquals('value', $this->cache->get('key1'));

        $this->cache->set('key2', 'value', new \DateInterval('PT2S'));
        static::assertEquals('value', $this->cache->get('key2'));

        $this->advanceTime(3);

        static::assertNull($this->cache->get('key1'), 'Value must expire after ttl.');
        static::assertNull($this->cache->get('key2'), 'Value must expire after ttl.');
    }

    public function testSetExpiredTtl(): void
    {
        $this->skipIf(__FUNCTION__);

        $this->cache->set('key0', 'value');
        $this->cache->set('key0', 'value', 0);
        static::assertNull($this->cache->get('key0'));
        static::assertFalse($this->cache->has('key0'));

        $this->cache->set('key1', 'value', -1);
        static::assertNull($this->cache->get('key1'));
        static::assertFalse($this->cache->has('key1'));
    }

    public function testGet(): void
    {
        $this->skipIf(__FUNCTION__);

        static::assertNull($this->cache->get('key'));
        static::assertEquals('foo', $this->cache->get('key', 'foo'));

        $this->cache->set('key', 'value');
        static::assertEquals('value', $this->cache->get('key', 'foo'));
    }

    public function testDelete(): void
    {
        $this->skipIf(__FUNCTION__);

        static::assertTrue($this->cache->delete('key'), 'Deleting a value that does not exist should return true');
        $this->cache->set('key', 'value');
        static::assertTrue($this->cache->delete('key'), 'Delete must return true on success');
        static::assertNull($this->cache->get('key'), 'Values must be deleted on delete()');
    }

    public function testClear(): void
    {
        $this->skipIf(__FUNCTION__);

        static::assertTrue($this->cache->clear(), 'Clearing an empty cache should return true');
        $this->cache->set('key', 'value');
        static::assertTrue($this->cache->clear(), 'Delete must return true on success');
        static::assertNull($this->cache->get('key'), 'Values must be deleted on clear()');
    }

    public function testSetMultiple(): void
    {
        $this->skipIf(__FUNCTION__);

        $result = $this->cache->setMultiple(['key0' => 'value0', 'key1' => 'value1']);
        static::assertTrue($result, 'setMultiple() must return true if success');
        static::assertEquals('value0', $this->cache->get('key0'));
        static::assertEquals('value1', $this->cache->get('key1'));
    }

    public function testSetMultipleWithIntegerArrayKey(): void
    {
        $this->skipIf(__FUNCTION__);

        $result = $this->cache->setMultiple(['0' => 'value0']);
        static::assertTrue($result, 'setMultiple() must return true if success');
        static::assertEquals('value0', $this->cache->get('0'));
    }

    /**
     * @medium
     */
    public function testSetMultipleTtl(): void
    {
        $this->skipIf(__FUNCTION__);

        $this->cache->setMultiple(['key2' => 'value2', 'key3' => 'value3'], 2);
        static::assertEquals('value2', $this->cache->get('key2'));
        static::assertEquals('value3', $this->cache->get('key3'));

        $this->cache->setMultiple(['key4' => 'value4'], new \DateInterval('PT2S'));
        static::assertEquals('value4', $this->cache->get('key4'));

        $this->advanceTime(3);
        static::assertNull($this->cache->get('key2'), 'Value must expire after ttl.');
        static::assertNull($this->cache->get('key3'), 'Value must expire after ttl.');
        static::assertNull($this->cache->get('key4'), 'Value must expire after ttl.');
    }

    public function testSetMultipleExpiredTtl(): void
    {
        $this->skipIf(__FUNCTION__);

        $this->cache->setMultiple(['key0' => 'value0', 'key1' => 'value1'], 0);
        static::assertNull($this->cache->get('key0'));
        static::assertNull($this->cache->get('key1'));
    }

    public function testSetMultipleWithGenerator(): void
    {
        $this->skipIf(__FUNCTION__);

        $gen = function () {
            yield 'key0' => 'value0';
            yield 'key1' => 'value1';
        };

        $this->cache->setMultiple($gen());
        static::assertEquals('value0', $this->cache->get('key0'));
        static::assertEquals('value1', $this->cache->get('key1'));
    }

    public function testGetMultiple(): void
    {
        $this->skipIf(__FUNCTION__);

        $result = $this->cache->getMultiple(['key0', 'key1']);
        $keys = [];
        foreach ($result as $i => $r) {
            $keys[] = $i;
            static::assertNull($r);
        }
        sort($keys);
        static::assertSame(['key0', 'key1'], $keys);

        $this->cache->set('key3', 'value');
        $result = $this->cache->getMultiple(['key2', 'key3', 'key4'], 'foo');
        $keys = [];
        foreach ($result as $key => $r) {
            $keys[] = $key;
            if ($key === 'key3') {
                static::assertEquals('value', $r);
            } else {
                static::assertEquals('foo', $r);
            }
        }
        sort($keys);
        static::assertSame(['key2', 'key3', 'key4'], $keys);
    }

    public function testGetMultipleWithGenerator(): void
    {
        $this->skipIf(__FUNCTION__);

        $gen = function () {
            yield 1 => 'key0';
            yield 1 => 'key1';
        };

        $this->cache->set('key0', 'value0');
        $result = $this->cache->getMultiple($gen());
        $keys = [];
        foreach ($result as $key => $r) {
            $keys[] = $key;
            if ($key === 'key0') {
                static::assertEquals('value0', $r);
            } elseif ($key === 'key1') {
                static::assertNull($r);
            } else {
                static::assertFalse(true, 'This should not happend');
            }
        }
        sort($keys);
        static::assertSame(['key0', 'key1'], $keys);
        static::assertEquals('value0', $this->cache->get('key0'));
        static::assertNull($this->cache->get('key1'));
    }

    public function testDeleteMultiple(): void
    {
        $this->skipIf(__FUNCTION__);

        static::assertTrue(
            $this->cache->deleteMultiple([]),
            'Deleting a empty array should return true',
        );
        static::assertTrue(
            $this->cache->deleteMultiple(['key']),
            'Deleting a value that does not exist should return true',
        );

        $this->cache->set('key0', 'value0');
        $this->cache->set('key1', 'value1');
        static::assertTrue($this->cache->deleteMultiple(['key0', 'key1']), 'Delete must return true on success');
        static::assertNull($this->cache->get('key0'), 'Values must be deleted on deleteMultiple()');
        static::assertNull($this->cache->get('key1'), 'Values must be deleted on deleteMultiple()');
    }

    public function testDeleteMultipleGenerator(): void
    {
        $this->skipIf(__FUNCTION__);

        $gen = function () {
            yield 1 => 'key0';
            yield 1 => 'key1';
        };
        $this->cache->set('key0', 'value0');
        static::assertTrue($this->cache->deleteMultiple($gen()), 'Deleting a generator should return true');

        static::assertNull($this->cache->get('key0'), 'Values must be deleted on deleteMultiple()');
        static::assertNull($this->cache->get('key1'), 'Values must be deleted on deleteMultiple()');
    }

    public function testHas(): void
    {
        $this->skipIf(__FUNCTION__);

        static::assertFalse($this->cache->has('key0'));
        $this->cache->set('key0', 'value0');
        static::assertTrue($this->cache->has('key0'));
    }

    public function testBasicUsageWithLongKey(): void
    {
        $this->skipIf(__FUNCTION__);

        $key = str_repeat('a', 300);

        static::assertFalse($this->cache->has($key));
        static::assertTrue($this->cache->set($key, 'value'));

        static::assertTrue($this->cache->has($key));
        static::assertSame('value', $this->cache->get($key));

        static::assertTrue($this->cache->delete($key));

        static::assertFalse($this->cache->has($key));
    }

    /**
     * @dataProvider invalidKeys
     */
    public function testGetInvalidKeys($key): void
    {
        $this->skipIf(__FUNCTION__);

        try {
            $this->cache->get($key);
        } catch (InvalidArgumentException | \TypeError $e) {
            static::assertTrue(true);

            return;
        } catch (\Throwable $e) {
            static::fail(sprintf(
                '::get() throws an unexpected %s exception with key',
                get_class($e),
            ));
        }

        static::fail('::get() should throw an exception with key');
    }

    /**
     * @dataProvider invalidKeys
     */
    public function testGetMultipleInvalidKeys($key): void
    {
        $this->skipIf(__FUNCTION__);

        try {
            $this->cache->getMultiple(['key1', $key, 'key2']);
        } catch (InvalidArgumentException | \TypeError $e) {
            static::assertTrue(true);

            return;
        } catch (\Throwable $e) {
            static::fail(sprintf(
                '::getMultiple() throws an unexpected %s exception with key',
                get_class($e),
            ));
        }

        static::fail('::getMultiple() should throw an exception with key');
    }

    /**
     * @dataProvider invalidKeys
     */
    public function testSetInvalidKeys($key): void
    {
        $this->skipIf(__FUNCTION__);

        try {
            $this->cache->set($key, 'foobar');
        } catch (InvalidArgumentException | \TypeError $e) {
            static::assertTrue(true);

            return;
        } catch (\Throwable $e) {
            static::fail(sprintf(
                '::set() throws an unexpected %s exception with key',
                get_class($e),
            ));
        }

        static::fail('::set() should throw an exception with key');
    }

    /**
     * @dataProvider invalidArrayKeys
     */
    public function testSetMultipleInvalidKeys($key): void
    {
        $this->skipIf(__FUNCTION__);
        $values = function () use ($key) {
            yield 'key1' => 'foo';
            yield $key => 'bar';
            yield 'key2' => 'baz';
        };

        try {
            $this->cache->setMultiple($values());
        } catch (InvalidArgumentException | \TypeError $e) {
            static::assertTrue(true);

            return;
        } catch (\Throwable $e) {
            static::fail(sprintf(
                '::setMultiple() throws an unexpected %s exception with key',
                get_class($e),
            ));
        }

        static::fail('::setMultiple() should throw an exception with key');
    }

    /**
     * @dataProvider invalidKeys
     */
    public function testHasInvalidKeys($key): void
    {
        $this->skipIf(__FUNCTION__);

        try {
            $this->cache->has($key);
        } catch (InvalidArgumentException | \TypeError $e) {
            static::assertTrue(true);

            return;
        } catch (\Throwable $e) {
            static::fail(sprintf(
                '::has() throws an unexpected %s exception with key',
                get_class($e),
            ));
        }
        static::fail('::has() should throw an exception with key');
    }

    /**
     * @dataProvider invalidKeys
     */
    public function testDeleteInvalidKeys($key): void
    {
        $this->skipIf(__FUNCTION__);

        try {
            $this->cache->delete($key);
        } catch (InvalidArgumentException | \TypeError $e) {
            static::assertTrue(true);

            return;
        } catch (\Throwable $e) {
            static::fail(sprintf(
                '::delete() throws an unexpected %s exception with key',
                get_class($e),
            ));
        }

        static::fail('::delete() should throw an exception with key');
    }

    /**
     * @dataProvider invalidKeys
     */
    public function testDeleteMultipleInvalidKeys($key): void
    {
        $this->skipIf(__FUNCTION__);

        try {
            $this->cache->deleteMultiple(['key1', $key, 'key2']);
        } catch (InvalidArgumentException | \TypeError $e) {
            static::assertTrue(true);

            return;
        } catch (\Throwable $e) {
            static::fail(sprintf(
                '::deleteMultiple() throws an unexpected %s exception with key',
                get_class($e),
            ));
        }

        static::fail('::deleteMultiple() should throw an exception with key');
    }

    public function testNullOverwrite(): void
    {
        $this->skipIf(__FUNCTION__);

        $this->cache->set('key', 5);
        $this->cache->set('key', null);

        static::assertNull($this->cache->get('key'), 'Setting null to a key must overwrite previous value');
    }

    public function testDataTypeString(): void
    {
        $this->skipIf(__FUNCTION__);

        $this->cache->set('key', '5');
        $result = $this->cache->get('key');
        static::assertTrue('5' === $result, 'Wrong data type. If we store a string we must get an string back.');
        static::assertTrue(is_string($result), 'Wrong data type. If we store a string we must get an string back.');
    }

    public function testDataTypeInteger(): void
    {
        $this->skipIf(__FUNCTION__);

        $this->cache->set('key', 5);
        $result = $this->cache->get('key');
        static::assertTrue(5 === $result, 'Wrong data type. If we store an int we must get an int back.');
        static::assertTrue(is_int($result), 'Wrong data type. If we store an int we must get an int back.');
    }

    public function testDataTypeFloat(): void
    {
        $this->skipIf(__FUNCTION__);

        $float = 1.23456789;
        $this->cache->set('key', $float);
        $result = $this->cache->get('key');
        static::assertTrue(is_float($result), 'Wrong data type. If we store float we must get an float back.');
        static::assertEquals($float, $result);
    }

    public function testDataTypeBoolean(): void
    {
        $this->skipIf(__FUNCTION__);

        $this->cache->set('key', false);
        $result = $this->cache->get('key');
        static::assertTrue(is_bool($result), 'Wrong data type. If we store boolean we must get an boolean back.');
        static::assertFalse($result);
        static::assertTrue($this->cache->has('key'), 'has() should return true when true are stored. ');
    }

    public function testDataTypeArray(): void
    {
        $this->skipIf(__FUNCTION__);

        $array = ['a' => 'foo', 2 => 'bar'];
        $this->cache->set('key', $array);
        $result = $this->cache->get('key');
        static::assertTrue(is_array($result), 'Wrong data type. If we store array we must get an array back.');
        static::assertEquals($array, $result);
    }

    public function testDataTypeObject(): void
    {
        $this->skipIf(__FUNCTION__);

        $object = new \stdClass();
        $object->a = 'foo';
        $this->cache->set('key', $object);
        $result = $this->cache->get('key');
        static::assertTrue(is_object($result), 'Wrong data type. If we store object we must get an object back.');
        static::assertEquals($object, $result);
    }

    public function testBinaryData(): void
    {
        $this->skipIf(__FUNCTION__);

        $data = '';
        for ($i = 0; $i < 256; $i++) {
            $data .= chr($i);
        }

        $array = ['a' => 'foo', 2 => 'bar'];
        $this->cache->set('key', $data);
        $result = $this->cache->get('key');
        static::assertTrue($data === $result, 'Binary data must survive a round trip.');
    }

    /**
     * @dataProvider validKeys
     */
    public function testSetValidKeys($key): void
    {
        $this->skipIf(__FUNCTION__);

        $this->cache->set($key, 'foobar');
        static::assertEquals('foobar', $this->cache->get($key));
    }

    /**
     * @dataProvider validKeys
     */
    public function testSetMultipleValidKeys($key): void
    {
        $this->skipIf(__FUNCTION__);

        $this->cache->setMultiple([$key => 'foobar']);
        $result = $this->cache->getMultiple([$key]);
        $keys = [];
        foreach ($result as $i => $r) {
            $keys[] = $i;
            static::assertEquals($key, $i);
            static::assertEquals('foobar', $r);
        }
        static::assertSame([$key], $keys);
    }

    /**
     * @dataProvider validData
     */
    public function testSetValidData($data): void
    {
        $this->skipIf(__FUNCTION__);

        $this->cache->set('key', $data);
        static::assertEquals($data, $this->cache->get('key'));
    }

    /**
     * @dataProvider validData
     */
    public function testSetMultipleValidData($data): void
    {
        $this->skipIf(__FUNCTION__);

        $expected = ['key' => $data];
        $this->cache->setMultiple($expected);
        $actual = $this->cache->getMultiple(array_keys($expected));
        $actualKeys = [];
        foreach ($actual as $actualKey => $actualData) {
            $actualKeys[] = $actualKey;
            static::assertEquals($data, $actualData);
        }
        static::assertSame(array_keys($expected), $actualKeys);
    }

    public function testObjectAsDefaultValue(): void
    {
        $this->skipIf(__FUNCTION__);

        $obj = new \stdClass();
        $obj->foo = 'value';
        static::assertEquals($obj, $this->cache->get('key', $obj));
    }

    public function testObjectDoesNotChangeInCache(): void
    {
        $this->skipIf(__FUNCTION__);

        $obj = new \stdClass();
        $obj->foo = 'value';
        $this->cache->set('key', $obj);
        $obj->foo = 'changed';

        $cacheObject = $this->cache->get('key');
        static::assertEquals('value', $cacheObject->foo, 'Object in cache should not have their values changed.');
    }
}
