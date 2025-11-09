<?php

namespace Core\Cache;

use Core\Contracts\Cache\StoreInterface;

class ArrayStore implements StoreInterface
{
    /**
     * The array of stored values.
     *
     * @var array
     */
    protected array $storage = [];

    /**
     * The cache key prefix.
     *
     * @var string
     */
    protected string $prefix;

    /**
     * Create a new array cache store instance.
     *
     * @param string $prefix
     */
    public function __construct(string $prefix = '')
    {
        $this->prefix = $prefix;
    }

    /**
     * Retrieve an item from the cache by key.
     *
     * @param string $key
     * @return mixed
     */
    public function get(string $key): mixed
    {
        $key = $this->prefix . $key;

        if (!isset($this->storage[$key])) {
            return null;
        }

        $data = $this->storage[$key];

        // Check if expired
        if (time() >= $data['expires_at']) {
            unset($this->storage[$key]);
            return null;
        }

        return $data['value'];
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
        $this->storage[$this->prefix . $key] = [
            'value' => $value,
            'expires_at' => time() + $seconds,
        ];

        return true;
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
        $current = $this->get($key);

        if ($current === null) {
            $current = 0;
        }

        if (!is_numeric($current)) {
            return false;
        }

        $newValue = ((int) $current) + $value;

        $key = $this->prefix . $key;
        $ttl = 3600; // Default 1 hour

        if (isset($this->storage[$key])) {
            $ttl = max(1, $this->storage[$key]['expires_at'] - time());
        }

        $this->put($key, $newValue, $ttl);

        return $newValue;
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
        return $this->increment($key, -$value);
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
        return $this->put($key, $value, 315360000);
    }

    /**
     * Remove an item from the cache.
     *
     * @param string $key
     * @return bool
     */
    public function forget(string $key): bool
    {
        unset($this->storage[$this->prefix . $key]);
        return true;
    }

    /**
     * Remove all items from the cache.
     *
     * @return bool
     */
    public function flush(): bool
    {
        $this->storage = [];
        return true;
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