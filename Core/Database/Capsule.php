<?php

namespace Core\Database;

use Core\Contracts\Database\ConnectionResolverInterface;
use Core\Exceptions\DatabaseException;

class Capsule
{
    protected static ?ConnectionResolverInterface $manager = null;

    public static function setManager(ConnectionResolverInterface $manager): void
    {
        static::$manager = $manager;
    }

    public static function getManager(): ConnectionResolverInterface
    {
        if (static::$manager === null) {
            throw new DatabaseException("Database manager not initialized. Call Capsule::setManager() first.");
        }

        return static::$manager;
    }

    /**
     * Add a connection at runtime
     *
     * @param array $config Connection configuration
     * @param string $name Connection name
     * @return void
     */
    public static function addConnection(array $config, string $name = 'default'): void
    {
        $manager = static::getManager();
        
        if (!method_exists($manager, 'addConnection')) {
            throw new DatabaseException("The current database manager does not support addConnection().");
        }
        
        $manager->addConnection($config, $name);
    }

    public static function connection(?string $name = null)
    {
        return static::getManager()->connection($name);
    }

    public static function table(string $table, ?Model $model = null)
    {
        return static::connection()->table($table, $model);
    }

    /**
     * Check if a connection exists
     *
     * @param string $name
     * @return bool
     */
    public static function hasConnection(string $name): bool
    {
        $manager = static::getManager();
        
        if (method_exists($manager, 'hasConnection')) {
            return $manager->hasConnection($name);
        }
        
        return false;
    }

    /**
     * Remove a connection
     *
     * @param string $name
     * @return void
     */
    public static function removeConnection(string $name): void
    {
        $manager = static::getManager();
        
        if (method_exists($manager, 'removeConnection')) {
            $manager->removeConnection($name);
        }
    }

    public static function __callStatic(string $method, array $parameters)
    {
        return static::connection()->$method(...$parameters);
    }
}