<?php

namespace UglyRobot\Infinite_Uploads\Aws;

use UglyRobot\Infinite_Uploads\Psr\SimpleCache\CacheInterface as SimpleCacheInterface;
class Psr16CacheAdapter implements \UglyRobot\Infinite_Uploads\Aws\CacheInterface
{
    /** @var SimpleCacheInterface */
    private $cache;
    public function __construct(\UglyRobot\Infinite_Uploads\Psr\SimpleCache\CacheInterface $cache)
    {
        $this->cache = $cache;
    }
    public function get($key)
    {
        return $this->cache->get($key);
    }
    public function set($key, $value, $ttl = 0)
    {
        $this->cache->set($key, $value, $ttl);
    }
    public function remove($key)
    {
        $this->cache->delete($key);
    }
}
