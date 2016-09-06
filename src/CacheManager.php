<?php

namespace NunoPress\Cache;

use Illuminate\Cache\CacheManager as IlluminateCacheManager;
use Illuminate\Cache\FileStore;
use Illuminate\Cache\MemcachedStore;
use Illuminate\Cache\RedisStore;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Pimple\Container;

/**
 * Class CacheManager
 * @package NunoPress\Cache
 */
class CacheManager extends IlluminateCacheManager
{
    /**
     * @var Container
     */
    protected $app;

    /**
     * @var array
     */
    protected $config = [];

    /**
     * @param Container $app
     */
    public function __construct(Container $app)
    {
        $this->app = $app;
    }

    /**
     * Create an instance of the Memcached cache driver.
     *
     * @param  array  $config
     * @return \Illuminate\Cache\MemcachedStore
     */
    protected function createMemcachedDriver(array $config)
    {
        $prefix = $this->getPrefix($config);

        $memcached = $this->app['cache.memcached.connector']->connect(
            $config['servers'],
            array_get($config, 'persistent_id'),
            array_get($config, 'options', []),
            array_filter(array_get($config, 'sasl', []))
        );
        return $this->repository(new MemcachedStore($memcached, $prefix));
    }

    /**
     * Create an instance of the Redis cache driver.
     *
     * @param  array  $config
     * @return \Illuminate\Cache\RedisStore
     */
    protected function createRedisDriver(array $config)
    {
        $redis = $this->app['cache.redis.connector'];

        $connection = Arr::get($config, 'connection', 'default');

        return $this->repository(new RedisStore($redis, $this->getPrefix($config), $connection));
    }

    /**
     * @param string $name
     * @return array
     */
    protected function getConfig($name)
    {
        return $this->config[$name];
    }

    /**
     * @return string
     */
    public function getDefaultProfile()
    {
        return $this->app['cache.profile.resolver']();
    }

    /**
     * @param array $config
     * @return string
     */
    protected function getPrefix(array $config)
    {
        return Arr::get($config, 'prefix') ?: $this->app['cache.prefix'];
    }

    /**
     * @param null|array $profile
     * @return Repository
     */
    public function store($profile = null)
    {
        $profile = $profile ?: $this->getDefaultProfile();

        return $this->stores[$profile] = $this->get($profile);
    }

    /**
     * @param string $profile
     * @return Repository
     */
    protected function get($profile)
    {
        return isset($this->stores[$profile]) ? $this->stores[$profile] : $this->resolve($profile);
    }

    /**
     * @param string $profile
     * @return Repository
     */
    protected function resolve($profile)
    {
        $config = isset($this->app['cache.profiles'][$profile]) ? $this->app['cache.profiles'][$profile] : null;

        if (is_null($config)) {
            throw new \InvalidArgumentException("Cache profile [{$profile}] is not defined.");
        }

        if (isset($this->customCreators[$config['driver']])) {
            return $this->callCustomCreator($config);
        } else {
            $driverMethod = 'create'.ucfirst($config['driver']).'Driver';

            // Fix missing parameters
            $config['parameters'] = isset($config['parameters']) ? $config['parameters'] : [];

            if (method_exists($this, $driverMethod)) {
                return $this->{$driverMethod}($config['parameters']);
            } else {
                throw new \InvalidArgumentException("Driver [{$config['driver']}] is not supported.");
            }
        }
    }

    /**
     * @param Store $store
     * @return Repository
     */
    public function repository(Store $store)
    {
        return new Repository($store);
    }

    /**
     * @param array $config
     * @return FileStore
     */
    protected function createFileDriver(array $config)
    {
        return $this->repository(new FileStore(new Filesystem(), $config['path']));
    }

    /**
     * @param array $config
     * @throws \Exception
     * @return null
     */
    protected function createDatabaseDriver(array $config)
    {
        throw new \Exception('Cache DB driver not supported.');
    }
}