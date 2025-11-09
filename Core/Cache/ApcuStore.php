<?php

namespace Core\Cache;

use Core\Contracts\Cache\StoreInterface;

class ApcuStore implements StoreInterface
{
    /**
     * The APCu key prefix.
     *
     * @var string
     */
    protected string $prefix;

    /**
     * Create a new APCu cache store instance.
     *
     * @param string $prefix
     */
    public function __construct(string $prefix = '')
    {
        $this->prefix = $prefix;

        if (!extension_loaded('apcu') || !\apcu_enabled()) {
            throw new \RuntimeException('APCu extension is not available or not enabled.');
        }
    }

    /**
     * Retrieve an item from the cache by key.
     *
     * @param string $key
     * @return mixed
     */
    public function get(string $key): mixed
    {
        $value = \apcu_fetch($this->prefix . $key, $success);

        return $success ? $value : null;
    }

    /**
     * Store an item in the cache for a given number of seconds.
     *
     * @param string $key
     * @param mixed $value
     * @param int $seconds
     * @return bool
     */
    public function put(string $key, mixed $value, int $seconds): bool
    {
        return \apcu_store($this->prefix . $key, $value, $seconds);
    }

    /**
     * Increment the value of an item in the cache.
     *
     * @param string $key
     * @param int $value
     * @return int|bool
     */
    public function increment(string $key, int $value = 1): int|bool
    {
        return \apcu_inc($this->prefix . $key, $value);
    }

    /**
     * Decrement the value of an item in the cache.
     *
     * @param string $key
     * @param int $value
     * @return int|bool
     */
    public function decrement(string $key, int $value = 1): int|bool
    {
        return \apcu_dec($this->prefix . $key, $value);
    }

    /**
     * Store an item in the cache indefinitely.
     *
     * @param string $key
     * @param mixed $value
     * @return bool
     */
    public function forever(string $key, mixed $value): bool
    {
        return $this->put($key, $value, 0);
    }

    /**
     * Remove an item from the cache.
     *
     * @param string $key
     * @return bool
     */
    public function forget(string $key): bool
    {
        return \apcu_delete($this->prefix . $key);
    }

    /**
     * Remove all items from the cache.
     *
     * @return bool
     */
    public function flush(): bool
    {
        return \apcu_clear_cache();
    }

    /**
     * Get the cache key prefix.
     *
     * @return string
     */
    public function getPrefix(): string
    {
        return $this->prefix;
    }
}