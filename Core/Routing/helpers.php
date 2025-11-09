<?php

if (!function_exists('base_path')) {
    /**
     * Get the base path of the application
     */
    function base_path(string $path = ''): string
    {
        $basePath = dirname(__DIR__);
        return $path ? $basePath . '/' . ltrim($path, '/') : $basePath;
    }
}

if (!function_exists('app_path')) {
    /**
     * Get the application path
     */
    function app_path(string $path = ''): string
    {
        return base_path('app' . ($path ? '/' . ltrim($path, '/') : ''));
    }
}

if (!function_exists('config_path')) {
    /**
     * Get the configuration path
     */
    function config_path(string $path = ''): string
    {
        return base_path('config' . ($path ? '/' . ltrim($path, '/') : ''));
    }
}

if (!function_exists('storage_path')) {
    /**
     * Get the storage path
     */
    function storage_path(string $path = ''): string
    {
        return base_path('storage' . ($path ? '/' . ltrim($path, '/') : ''));
    }
}

if (!function_exists('public_path')) {
    /**
     * Get the public path
     */
    function public_path(string $path = ''): string
    {
        return base_path('public' . ($path ? '/' . ltrim($path, '/') : ''));
    }
}

if (!function_exists('env')) {
    /**
     * Get environment variable using the Env class.
     */
    function env(string $key, mixed $default = null): mixed
    {
        // Lazy-load support class if not already loaded
        static $env;

        if ($env === null) {
            $env = new \Core\Support\Env();
        }

        return $env::get($key, $default);
    }
}
