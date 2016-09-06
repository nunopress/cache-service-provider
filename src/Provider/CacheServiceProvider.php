<?php

namespace NunoPress\Cache\Provider;

use Illuminate\Cache\MemcachedConnector;
use NunoPress\Cache\CacheManager;
use Pimple\Container;
use Pimple\ServiceProviderInterface;

/**
 * Class CacheServiceProvider
 * @package NunoPress\Cache\Provider
 */
class CacheServiceProvider implements ServiceProviderInterface
{
    /**
     * @param Container $app
     */
    public function register(Container $app)
    {
        /**
         * @return string
         */
        $app['cache.prefix'] = '';

        /**
         * @return array
         */
        $app['cache.default_options'] = $app->protect(function () {
            return [
                'driver' => 'array',
                'parameters' => []
            ];
        });

        /**
         * @return array
         */
        $app['cache.profiles.initializer'] = $app->protect(function () use ($app) {
            static $initialized = false;

            if (true === $initialized) {
                return;
            }

            $initialized = true;

            if (false === isset($app['cache.profiles'])) {
                $app['cache.profiles'] = [
                    'default' => $app['cache.default_options']
                ];
            }
        });

        /*
         * @deprecated
         *
         * todo: removed in the future version because this service call every driver so degrade performance.
         */
        $app['cache.profiles.factory'] = function () use ($app) {
            $app['cache.profiles.initializer']();

            $container = new Container();

            foreach ($app['cache.profiles'] as $name => $options) {
                // merge with default options
                $options = array_merge($app['cache.default_options'], $options);

                $container[$name] = function () use ($app, $options) {
                    $cache = $app['cache.manager']->store($options['driver'], $options['parameters']);

                    return $cache;
                };
            }

            return $container;
        };

        /**
         * @param Container $app
         * @return CacheManager
         */
        $app['cache.manager'] = function (Container $app) {
            $app['cache.profiles.initializer']();

            return new CacheManager($app);
        };

        /**
         * @return string
         */
        $app['cache.profile.resolver'] = $app->protect(function ($profile = null) use ($app) {
            return $profile ?: @key($app['cache.profiles']);
        });

        /**
         * @param Container $app
         * @return MemcachedConnector
         */
        $app['cache.memcached.connector'] = function (Container $app) {
            return new MemcachedConnector();
        };

        /**
         * todo: need testing
         *
         * @param Container $app
         * @return \Redis
         */
        $app['cache.redis.connector'] = function (Container $app) {
            return new \Redis();
        };

        /**
         * @param Container $app
         * @return \Illuminate\Contracts\Cache\Repository
         */
        $app['cache'] = function (Container $app) {
            return $app['cache.manager']->store($app['cache.profile.resolver']());
        };
    }
}