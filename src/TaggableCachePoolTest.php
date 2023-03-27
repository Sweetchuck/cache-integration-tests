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

use Cache\TagInterop\TaggableCacheItemPoolInterface;
use PHPUnit\Framework\TestCase;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
abstract class TaggableCachePoolTest extends TestCase
{
    /**
     * With functionName => reason.
     *
     * @phpstan-var array<string, string>
     */
    protected array $skippedTests = [];

    protected ?TaggableCacheItemPoolInterface $cache;

    abstract public function createCachePool(): TaggableCacheItemPoolInterface;

    protected function skipIf(string $function): void
    {
        if (isset($this->skippedTests[$function])) {
            static::markTestSkipped($this->skippedTests[$function]);
        }
    }

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

    public static function invalidKeys(): array
    {
        return CachePoolTest::invalidKeys();
    }

    public function testMultipleTags(): void
    {
        $this->skipIf(__FUNCTION__);

        $this->cache->save($this->cache->getItem('key1')->set('value')->setTags(['tag1', 'tag2']));
        $this->cache->save($this->cache->getItem('key2')->set('value')->setTags(['tag1', 'tag3']));
        $this->cache->save($this->cache->getItem('key3')->set('value')->setTags(['tag2', 'tag3']));
        $this->cache->save($this->cache->getItem('key4')->set('value')->setTags(['tag4', 'tag3']));

        $this->cache->invalidateTags(['tag1']);
        static::assertFalse($this->cache->hasItem('key1'));
        static::assertFalse($this->cache->hasItem('key2'));
        static::assertTrue($this->cache->hasItem('key3'));
        static::assertTrue($this->cache->hasItem('key4'));

        $this->cache->invalidateTags(['tag2']);
        static::assertFalse($this->cache->hasItem('key1'));
        static::assertFalse($this->cache->hasItem('key2'));
        static::assertFalse($this->cache->hasItem('key3'));
        static::assertTrue($this->cache->hasItem('key4'));
    }

    public function testPreviousTag(): void
    {
        $this->skipIf(__FUNCTION__);

        $item = $this->cache->getItem('key')->set('value');
        $tags = $item->getPreviousTags();
        static::assertTrue(is_array($tags));
        static::assertCount(0, $tags);

        $item->setTags(['tag0']);
        static::assertCount(0, $item->getPreviousTags());

        $this->cache->save($item);
        static::assertCount(0, $item->getPreviousTags());

        $item = $this->cache->getItem('key');
        static::assertCount(1, $item->getPreviousTags());
    }

    public function testPreviousTagDeferred(): void
    {
        $this->skipIf(__FUNCTION__);

        $item = $this->cache->getItem('key')->set('value');
        $item->setTags(['tag0']);
        static::assertCount(0, $item->getPreviousTags());

        $this->cache->saveDeferred($item);
        static::assertCount(0, $item->getPreviousTags());

        $item = $this->cache->getItem('key');
        static::assertCount(1, $item->getPreviousTags());
    }

    public function testTagAccessorWithEmptyTag(): void
    {
        $this->skipIf(__FUNCTION__);

        $item = $this->cache->getItem('key')->set('value');
        $this->expectException('Psr\Cache\InvalidArgumentException');
        $item->setTags(['']);
    }

    /**
     * @dataProvider invalidKeys
     */
    public function testTagAccessorWithInvalidTag($tag): void
    {
        $this->skipIf(__FUNCTION__);

        $item = $this->cache->getItem('key')->set('value');
        $this->expectException('Psr\Cache\InvalidArgumentException');
        $item->setTags([$tag]);
    }

    public function testTagAccessorDuplicateTags(): void
    {
        $this->skipIf(__FUNCTION__);

        $item = $this->cache->getItem('key')->set('value');
        $item->setTags(['tag', 'tag', 'tag']);
        $this->cache->save($item);
        $item = $this->cache->getItem('key');

        static::assertCount(1, $item->getPreviousTags());
    }

    /**
     * The tag must be removed whenever we remove an item. If not, when creating a new item
     * with the same key will get the same tags.
     */
    public function testRemoveTagWhenItemIsRemoved(): void
    {
        $this->skipIf(__FUNCTION__);

        $item = $this->cache->getItem('key')->set('value');
        $item->setTags(['tag1']);

        // Save the item and then delete it
        $this->cache->save($item);
        $this->cache->deleteItem('key');

        // Create a new item (same key) (no tags)
        $item = $this->cache->getItem('key')->set('value');
        $this->cache->save($item);

        // Clear the tag, The new item should not be cleared
        $this->cache->invalidateTags(['tag1']);
        static::assertTrue(
            $this->cache->hasItem('key'),
            'Item key should be removed from the tag list when the item is removed',
        );
    }

    public function testClearPool(): void
    {
        $this->skipIf(__FUNCTION__);

        $item = $this->cache->getItem('key')->set('value');
        $item->setTags(['tag1']);
        $this->cache->save($item);

        // Clear the pool
        $this->cache->clear();

        // Create a new item (no tags)
        $item = $this->cache->getItem('key')->set('value');
        $this->cache->save($item);
        $this->cache->invalidateTags(['tag1']);

        static::assertTrue($this->cache->hasItem('key'), 'Tags should be removed when the pool was cleared.');
    }

    public function testInvalidateTag(): void
    {
        $this->skipIf(__FUNCTION__);

        $item = $this->cache->getItem('key')->set('value');
        $item->setTags(['tag1', 'tag2']);
        $this->cache->save($item);
        $item = $this->cache->getItem('key2')->set('value');
        $item->setTags(['tag1']);
        $this->cache->save($item);

        $this->cache->invalidateTag('tag2');
        static::assertFalse($this->cache->hasItem('key'), 'Item should be cleared when tag is invalidated');
        static::assertTrue($this->cache->hasItem('key2'), 'Item should be cleared when tag is invalidated');

        // Create a new item (no tags)
        $item = $this->cache->getItem('key')->set('value');
        $this->cache->save($item);
        $this->cache->invalidateTags(['tag2']);
        static::assertTrue($this->cache->hasItem('key'), 'Item key list should be removed when clearing the tags');

        $this->cache->invalidateTags(['tag1']);
        static::assertTrue($this->cache->hasItem('key'), 'Item key list should be removed when clearing the tags');
    }

    public function testInvalidateTags(): void
    {
        $this->skipIf(__FUNCTION__);

        $item = $this->cache->getItem('key')->set('value');
        $item->setTags(['tag1', 'tag2']);
        $this->cache->save($item);
        $item = $this->cache->getItem('key2')->set('value');
        $item->setTags(['tag1']);
        $this->cache->save($item);

        $this->cache->invalidateTags(['tag1', 'tag2']);
        static::assertFalse($this->cache->hasItem('key'), 'Item should be cleared when tag is invalidated');
        static::assertFalse($this->cache->hasItem('key2'), 'Item should be cleared when tag is invalidated');

        // Create a new item (no tags)
        $item = $this->cache->getItem('key')->set('value');
        $this->cache->save($item);
        $this->cache->invalidateTags(['tag1']);

        static::assertTrue($this->cache->hasItem('key'), 'Item k list should be removed when clearing the tags');
    }

    /**
     * When an item is overwritten we need to clear tags for original item.
     */
    public function testTagsAreCleanedOnSave(): void
    {
        $this->skipIf(__FUNCTION__);

        $pool = $this->cache;
        $i    = $pool->getItem('key')->set('value');
        $pool->save($i->setTags(['foo']));
        $i = $pool->getItem('key');
        $pool->save($i->setTags(['bar']));
        $pool->invalidateTags(['foo']);
        static::assertTrue($pool->getItem('key')->isHit());
    }
}
