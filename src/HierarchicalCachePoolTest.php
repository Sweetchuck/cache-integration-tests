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
use Psr\Cache\CacheItemPoolInterface;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
abstract class HierarchicalCachePoolTest extends TestCase
{
    /**
     * With functionName => reason.
     *
     * @phpstan-var array<string, string>
     */
    protected array $skippedTests = [];

    protected ?CacheItemPoolInterface $cache;

    abstract public function createCachePool(): CacheItemPoolInterface;

    /**
     * @before
     */
    public function setupService()
    {
        $this->cache = $this->createCachePool();
    }

    /**
     * @after
     */
    public function tearDownService()
    {
        $this->cache?->clear();
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

        $user = 4711;
        for ($i = 0; $i < 10; $i++) {
            $item = $this->cache->getItem(sprintf('|users|%d|followers|%d|likes', $user, $i));
            $item->set('Justin Bieber');
            $this->cache->save($item);
        }

        static::assertTrue($this->cache->hasItem('|users|4711|followers|4|likes'));
        $this->cache->deleteItem('|users|4711|followers');
        static::assertFalse(
            $this->cache->hasItem('|users|4711|followers|4|likes'),
            'child item also deleted when the parent gets deleted',
        );
    }

    public function testChain(): void
    {
        $this->skipIf(__FUNCTION__);

        $item = $this->cache->getItem('|aaa|bbb|ccc|ddd');
        $item->set('value');
        $this->cache->save($item);

        $item = $this->cache->getItem('|aaa|bbb|ccc|xxx');
        $item->set('value');
        $this->cache->save($item);

        $item = $this->cache->getItem('|aaa|bbb|zzz|ddd');
        $item->set('value');
        $this->cache->save($item);

        static::assertTrue($this->cache->hasItem('|aaa|bbb|ccc|ddd'));
        static::assertTrue($this->cache->hasItem('|aaa|bbb|ccc|xxx'));
        static::assertTrue($this->cache->hasItem('|aaa|bbb|zzz|ddd'));
        static::assertFalse($this->cache->hasItem('|aaa|bbb|ccc'));
        static::assertFalse($this->cache->hasItem('|aaa|bbb|zzz'));
        static::assertFalse($this->cache->hasItem('|aaa|bbb'));
        static::assertFalse($this->cache->hasItem('|aaa'));
        static::assertFalse($this->cache->hasItem('|'));

        // This is a different thing
        $this->cache->deleteItem('|aaa|bbb|cc');
        static::assertTrue($this->cache->hasItem('|aaa|bbb|ccc|ddd'));
        static::assertTrue($this->cache->hasItem('|aaa|bbb|ccc|xxx'));
        static::assertTrue($this->cache->hasItem('|aaa|bbb|zzz|ddd'));

        $this->cache->deleteItem('|aaa|bbb|ccc');
        static::assertFalse($this->cache->hasItem('|aaa|bbb|ccc|ddd'));
        static::assertFalse($this->cache->hasItem('|aaa|bbb|ccc|xxx'));
        static::assertTrue($this->cache->hasItem('|aaa|bbb|zzz|ddd'));

        $this->cache->deleteItem('|aaa');
        static::assertFalse($this->cache->hasItem('|aaa|bbb|zzz|ddd'));
    }

    public function testRemoval(): void
    {
        $this->skipIf(__FUNCTION__);

        $item = $this->cache->getItem('foo');
        $item->set('value');
        $this->cache->save($item);

        $item = $this->cache->getItem('|aaa|bbb');
        $item->set('value');
        $this->cache->save($item);

        $this->cache->deleteItem('|');
        static::assertFalse($this->cache->hasItem('|aaa|bbb'), 'Hierarchy items should be removed when deleting root');
        static::assertTrue($this->cache->hasItem('foo'), 'All cache should not be cleared when deleting root');
    }

    public function testRemovalWhenDeferred(): void
    {
        $this->skipIf(__FUNCTION__);

        $item = $this->cache->getItem('|aaa|bbb');
        $item->set('value');
        $this->cache->saveDeferred($item);

        $this->cache->deleteItem('|');
        static::assertFalse($this->cache->hasItem('|aaa|bbb'), 'Deferred hierarchy items should be removed');

        $this->cache->commit();
        static::assertFalse($this->cache->hasItem('|aaa|bbb'), 'Deferred hierarchy items should be removed');
    }
}
