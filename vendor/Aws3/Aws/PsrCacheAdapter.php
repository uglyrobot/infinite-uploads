<?php

namespace UglyRobot\Infinite_Uploads\Aws;

use UglyRobot\Infinite_Uploads\Psr\Cache\CacheItemPoolInterface;
class PsrCacheAdapter implements \UglyRobot\Infinite_Uploads\Aws\CacheInterface
{
    /** @var CacheItemPoolInterface */
    private $pool;
    public function __construct(\UglyRobot\Infinite_Uploads\Psr\Cache\CacheItemPoolInterface $pool)
    {
        $this->pool = $pool;
    }
    public function get($key)
    {
        $item = $this->pool->getItem($key);
        return $item->isHit() ? $item->get() : null;
    }
    public function set($key, $value, $ttl = 0)
    {
        $item = $this->pool->getItem($key);
        $item->set($value);
        if ($ttl > 0) {
            $item->expiresAfter($ttl);
        }
        $this->pool->save($item);
    }
    public function remove($key)
    {
        $this->pool->deleteItem($key);
    }
}
