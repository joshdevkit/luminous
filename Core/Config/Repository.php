<?php

namespace Core\Config;

class Repository
{
    protected array $items = [];

    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    /**
     * Get a configuration value using dot notation.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        // Direct access (non-dot key)
        if (array_key_exists($key, $this->items)) {
            return $this->items[$key];
        }

        // Dot-notation access
        $segments = explode('.', $key);
        $data = $this->items;

        foreach ($segments as $segment) {
            if (is_array($data) && array_key_exists($segment, $data)) {
                $data = $data[$segment];
            } else {
                return $default;
            }
        }

        return $data;
    }

    /**
     * Set a configuration value (supports dot notation).
     */
    public function set(string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $data =& $this->items;

        foreach ($segments as $segment) {
            if (!isset($data[$segment]) || !is_array($data[$segment])) {
                $data[$segment] = [];
            }

            $data =& $data[$segment];
        }

        $data = $value;
    }

    /**
     * Check if a key exists (supports dot notation).
     */
    public function has(string $key): bool
    {
        $segments = explode('.', $key);
        $data = $this->items;

        foreach ($segments as $segment) {
            if (is_array($data) && array_key_exists($segment, $data)) {
                $data = $data[$segment];
            } else {
                return false;
            }
        }

        return true;
    }

    public function all(): array
    {
        return $this->items;
    }

    public function load(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        $items = require $path;

        if (is_array($items)) {
            $this->items = array_merge($this->items, $items);
        }
    }

    public function loadDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $files = glob($directory . '/*.php');

        foreach ($files as $file) {
            $name = basename($file, '.php');
            $this->items[$name] = require $file;
        }
    }
}
