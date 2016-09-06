<?php

namespace NunoPress\Cache\Application;

/**
 * Class CacheTrait
 * @package NunoPress\Cache\Application
 */
trait CacheTrait
{
    /**
     * @param null|string $profile
     * @return \Illuminate\Contracts\Cache\Repository
     */
    public function cache($profile = null)
    {
        return $this['cache.manager']->store($this['cache.profile.resolver']($profile));
    }
}