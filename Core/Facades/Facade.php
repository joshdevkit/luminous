<?php

namespace Core\Facades;

abstract class Facade
{
    /**
     * Get the registered name or class from the service container
     */
    protected static function getFacadeAccessor(): string
    {
        throw new \RuntimeException('Facade does not implement getFacadeAccessor method.');
    }

    /**
     * Handle dynamic static calls
     */
    public static function __callStatic(string $method, array $args)
    {
        $accessor = static::getFacadeAccessor();

        $instance = app()->get($accessor);

        if (!$instance) {
            throw new \RuntimeException("Service [{$accessor}] not found in container.");
        }

        return $instance->$method(...$args);
    }
}
