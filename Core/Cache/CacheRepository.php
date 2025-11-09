<?php

namespace Core\Cache;

use Core\Contracts\Cache\CacheInterface;
use Core\Contracts\Cache\StoreInterface;
use DateTimeInterface;
use DateInterval;

class CacheRepository implements CacheInterface
{
    /**
     * The cache store implementation.
     *
     * @var StoreInterface
     */
    protected StoreInterface $store;

    /**
     * The default cache TTL in seconds.
     *
     * @var int
     */
    protected int $defaultTtl = 3600;

    /**
     * Create a new cache repository instance.
     *
     * @param StoreInterface $store
     * @param int $defaultTtl
     */
    public function __construct(StoreInterface $store, int $defaultTtl = 3600)
    {
        $this->store = $store;
        $this->defaultTtl = $defaultTtl;
    }

    /**
     * Retrieve an item from the cache.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->store->get($key);

        return $value !== null ? $value : value($default);
    }

    /**
     * Store an item in the cache.
     *
     * @param string $key
     * @param mixed $value
     * @param int|DateTimeInterface|DateInterval|null $ttl
     * @return bool
     */
    public function put(string $key, mixed $value, int|DateTimeInterface|DateInterval|null $ttl = null): bool
    {
        $seconds = $this->getSeconds($ttl);

        if ($seconds <= 0) {
            return $this->forget($key);
        }

        return $this->store->put($key, $value, $seconds);
    }

    /**
     * Store an item in the cache if the key does not exist.
     *
     * @param string $key
     * @param mixed $value
     * @param int|DateTimeInterface|DateInterval|null $ttl
     * @return bool
     */
    public function add(string $key, mixed $value, int|DateTimeInterface|DateInterval|null $ttl = null): bool
    {
        if ($this->has($key)) {
            return false;
        }

        return $this->put($key, $value, $ttl);
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
        return $this->store->increment($key, $value);
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
        return $this->store->decrement($key, $value);
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
        return $this->store->forever($key, $value);
    }

    /**
     * Remove an item from the cache.
     *
     * @param string $key
     * @return bool
     */
    public function forget(string $key): bool
    {
        return $this->store->forget($key);
    }

    /**
     * Remove all items from the cache.
     *
     * @return bool
     */
    public function flush(): bool
    {
        return $this->store->flush();
    }

    /**
     * Determine if an item exists in the cache.
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    /**
     * Retrieve an item from the cache and delete it.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);
        $this->forget($key);
        return $value;
    }

    /**
     * Get multiple items from the cache.
     *
     * @param array $keys
     * @return array
     */
    public function many(array $keys): array
    {
        $values = [];

        foreach ($keys as $key) {
            $values[$key] = $this->get($key);
        }

        return $values;
    }

    /**
     * Store multiple items in the cache.
     *
     * @param array $values
     * @param int|DateTimeInterface|DateInterval|null $ttl
     * @return bool
     */
    public function putMany(array $values, int|DateTimeInterface|DateInterval|null $ttl = null): bool
    {
        $success = true;

        foreach ($values as $key => $value) {
            if (!$this->put($key, $value, $ttl)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Get an item from the cache, or execute the given Closure and store the result.
     *
     * @param string $key
     * @param int|DateTimeInterface|DateInterval|null $ttl
     * @param \Closure $callback
     * @return mixed
     */
    public function remember(string $key, int|DateTimeInterface|DateInterval|null $ttl, \Closure $callback): mixed
    {
        $value = $this->get($key);

        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->put($key, $value, $ttl);

        return $value;
    }

    /**
     * Get an item from the cache, or execute the given Closure and store the result forever.
     *
     * @param string $key
     * @param \Closure $callback
     * @return mixed
     */
    public function rememberForever(string $key, \Closure $callback): mixed
    {
        $value = $this->get($key);

        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->forever($key, $value);

        return $value;
    }

    /**
     * Get the cache store implementation.
     *
     * @return StoreInterface
     */
    public function getStore(): StoreInterface
    {
        return $this->store;
    }

    /**
     * Calculate the number of seconds for the given TTL.
     *
     * @param int|DateTimeInterface|DateInterval|null $ttl
     * @return int
     */
    protected function getSeconds(int|DateTimeInterface|DateInterval|null $ttl): int
    {
        if ($ttl === null) {
            return $this->defaultTtl;
        }

        if ($ttl instanceof DateTimeInterface) {
            return max(0, $ttl->getTimestamp() - time());
        }

        if ($ttl instanceof DateInterval) {
            return (new \DateTime())->add($ttl)->getTimestamp() - time();
        }

        return (int) $ttl;
    }
}