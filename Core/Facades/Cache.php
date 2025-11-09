<?php

namespace Core\Facades;

/**
 * Class Cache
 *
 * @package Core\Facades
 *
 * @method static mixed get(string $key, mixed $default = null)
 * @method static bool put(string $key, mixed $value, int $ttl = null)
 * @method static bool has(string $key)
 * @method static bool forget(string $key)
 * @method static bool flush()
 * @method static mixed remember(string $key, int $ttl, \Closure $callback)
 * @method static \Core\Cache\CacheRepository store(string|null $name = null)
 *
 * @see \Core\Cache\CacheManager
 *
 * @description Facade for the Cache system, providing a static interface
 *              for interacting with the configured cache driver.
 */
class Cache extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string The service container binding name for cache.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'cache';
    }
}
