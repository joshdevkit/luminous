<?php

namespace Core\Cache;

use Core\Contracts\Cache\StoreInterface;

class FileStore implements StoreInterface
{
    /**
     * The file cache directory.
     *
     * @var string
     */
    protected string $directory;

    /**
     * The file cache key prefix.
     *
     * @var string
     */
    protected string $prefix;

    /**
     * Create a new file cache store instance.
     *
     * @param string $directory
     * @param string $prefix
     */
    public function __construct(string $directory, string $prefix = '')
    {
        $this->directory = $directory;
        $this->prefix = $prefix;

        $this->ensureDirectoryExists();
    }

    /**
     * Retrieve an item from the cache by key.
     *
     * @param string $key
     * @return mixed
     */
    public function get(string $key): mixed
    {
        $path = $this->path($key);

        if (!file_exists($path)) {
            return null;
        }

        $contents = @file_get_contents($path);

        if ($contents === false) {
            return null;
        }

        $data = unserialize($contents);

        // Check if expired
        if (time() >= $data['expires_at']) {
            $this->forget($key);
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
        $path = $this->path($key);

        $data = [
            'value' => $value,
            'expires_at' => time() + $seconds,
        ];

        $result = @file_put_contents($path, serialize($data), LOCK_EX);

        return $result !== false;
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
            // If key doesn't exist, start at 0
            $current = 0;
        }

        if (!is_numeric($current)) {
            return false;
        }

        $newValue = ((int) $current) + $value;

        // Get the remaining TTL from the existing cache entry
        $path = $this->path($key);
        $ttl = 3600; // Default 1 hour

        if (file_exists($path)) {
            $contents = @file_get_contents($path);
            if ($contents !== false) {
                $data = unserialize($contents);
                $ttl = max(1, $data['expires_at'] - time());
            }
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
        // Store for 10 years (effectively forever)
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
        $path = $this->path($key);

        if (file_exists($path)) {
            return @unlink($path);
        }

        return true;
    }

    /**
     * Remove all items from the cache.
     *
     * @return bool
     */
    public function flush(): bool
    {
        $files = glob($this->directory . '/*');

        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

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

    /**
     * Get the full path for a cache key.
     *
     * @param string $key
     * @return string
     */
    protected function path(string $key): string
    {
        $hash = hash('sha256', $this->prefix . $key);

        // Create subdirectories based on first 2 chars of hash (for better file distribution)
        $subdir = substr($hash, 0, 2);
        $directory = $this->directory . '/' . $subdir;

        if (!is_dir($directory)) {
            @mkdir($directory, 0755, true);
        }

        return $directory . '/' . $hash;
    }

    /**
     * Ensure the cache directory exists.
     *
     * @return void
     */
    protected function ensureDirectoryExists(): void
    {
        if (!is_dir($this->directory)) {
            @mkdir($this->directory, 0755, true);
        }
    }
}