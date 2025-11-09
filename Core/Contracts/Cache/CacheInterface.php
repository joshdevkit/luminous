<?php

namespace Core\Contracts\Cache;

interface CacheInterface
{
    /**
     * Retrieve an item from the cache.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Store an item in the cache.
     *
     * @param string $key
     * @param mixed $value
     * @param int|\DateTimeInterface|\DateInterval|null $ttl Time in seconds or DateTimeInterface
     * @return bool
     */
    public function put(string $key, mixed $value, int|\DateTimeInterface|\DateInterval|null $ttl = null): bool;

    /**
     * Store an item in the cache if the key does not exist.
     *
     * @param string $key
     * @param mixed $value
     * @param int|\DateTimeInterface|\DateInterval|null $ttl
     * @return bool
     */
    public function add(string $key, mixed $value, int|\DateTimeInterface|\DateInterval|null $ttl = null): bool;

    /**
     * Increment the value of an item in the cache.
     *
     * @param string $key
     * @param int $value
     * @return int|bool
     */
    public function increment(string $key, int $value = 1): int|bool;

    /**
     * Decrement the value of an item in the cache.
     *
     * @param string $key
     * @param int $value
     * @return int|bool
     */
    public function decrement(string $key, int $value = 1): int|bool;

    /**
     * Store an item in the cache indefinitely.
     *
     * @param string $key
     * @param mixed $value
     * @return bool
     */
    public function forever(string $key, mixed $value): bool;

    /**
     * Remove an item from the cache.
     *
     * @param string $key
     * @return bool
     */
    public function forget(string $key): bool;

    /**
     * Remove all items from the cache.
     *
     * @return bool
     */
    public function flush(): bool;

    /**
     * Determine if an item exists in the cache.
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool;

    /**
     * Retrieve an item from the cache and delete it.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function pull(string $key, mixed $default = null): mixed;

    /**
     * Get multiple items from the cache.
     *
     * @param array $keys
     * @return array
     */
    public function many(array $keys): array;

    /**
     * Store multiple items in the cache.
     *
     * @param array $values
     * @param int|\DateTimeInterface|\DateInterval|null $ttl
     * @return bool
     */
    public function putMany(array $values, int|\DateTimeInterface|\DateInterval|null $ttl = null): bool;
}