<?php

namespace Story\Provider;

use Doctrine\Common\Cache\ApcCache;
use Silex\Application;
use Silex\ServiceProviderInterface;

class CacheServiceProvider implements ServiceProviderInterface
{
    public function register(Application $app)
    {
        $app['cache'] = $app->share(
            function ($app) {
                return new ApcCacheWrapper();
            }
        );
    }

    public function boot(Application $app)
    {
    }
}

class ApcCacheWrapper
{
    private $cache;

    public function __construct()
    {
        $this->cache = new ApcCache();
        $this->cache->setNamespace('story_charging');
    }

    public function fetch($key, $fetcher, $ttl = 0)
    {
        $result = $this->cache->fetch($key);
        if (!$result) {
            $result = $fetcher();
            $this->cache->save($key, $result, $ttl);
        }

        return $result;
    }

    public function delete($key)
    {
        return $this->cache->delete($key);
    }
}
