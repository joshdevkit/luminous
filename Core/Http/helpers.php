<?php

use Core\Contracts\Session\SessionInterface;

if (!function_exists('session')) {
    /**
     * Get or set session data.
     *
     * - session() → returns the session instance
     * - session('key') → returns the session value
     * - session(['key' => 'value']) → sets a session key
     */
    function session($key = null, $value = null)
    {
        // The app('session') binding already returns the Session instance
        /** @var SessionInterface $session */
        $session = app('session');

        // No arguments → return the session instance
        if (is_null($key)) {
            return $session;
        }

        // Set multiple values
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $session->put($k, $v);
            }
            return;
        }

        // Set a single key/value
        if (!is_null($value)) {
            $session->put($key, $value);
            return;
        }

        // Otherwise, get a single key
        return $session->get($key);
    }
}


if (!function_exists('cache')) {
    /**
     * Get the cache manager or retrieve/store a cached value.
     *
     * @param string|array|null $key
     * @param mixed $default
     * @return mixed|CacheManager
     */
    function cache(string|array|null $key = null, mixed $default = null): mixed
    {
        $cache = app('cache');

        if (is_null($key)) {
            return $cache;
        }

        if (is_array($key)) {
            return $cache->putMany($key);
        }

        return $cache->get($key, $default);
    }
}