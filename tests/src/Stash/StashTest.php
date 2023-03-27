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

namespace Cache\IntegrationTests\Test\Stash;

use Cache\IntegrationTests\CachePoolTest as BaseTest;
use Psr\Cache\CacheItemPoolInterface;
use Stash\Driver\Redis;
use Stash\Interfaces\DriverInterface;
use Stash\Pool;

class StashTest extends BaseTest
{
    protected ?DriverInterface $client = null;

    public function createCachePool(): CacheItemPoolInterface
    {
        return new Pool($this->getClient());
    }

    protected function getClient(): DriverInterface
    {
        if ($this->client === null) {
            $this->client = new Redis([
                'servers' => $this->getRedisServers(),
            ]);
        }

        return $this->client;
    }

    /**
     * @phpstan-return array<array{server: string, port: int}>
     */
    protected function getRedisServers(): array
    {
        $definitions = [
            'url1' => getenv('CACHE_REDIS_SERVER_URL1') ?: '127.0.0.1:6379',
        ];

        $index = 1;
        while ($host = getenv("CACHE_REDIS_SERVER{$index}_HOST")) {
            $port = ((int) getenv("CACHE_REDIS_SERVER{$index}_PORT")) ?: -1;
            $servers[] = [
                'server' => $host,
                'port' => $port,
            ];

            $index++;
        }

        return $servers;
    }
}
