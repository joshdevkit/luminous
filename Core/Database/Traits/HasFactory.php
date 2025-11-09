<?php

namespace Core\Database\Traits;

use Core\Database\Generators\Factory;

trait HasFactory
{
    /**
     * Get a new factory instance for the model.
     */
    public static function factory(?int $count = null, array $state = []): Factory
    {
        $factory = static::newFactory();

        if ($count !== null) {
            $factory = $factory->count($count);
        }

        if (!empty($state)) {
            $factory = $factory->state($state);
        }

        return $factory;
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): Factory
    {
        // Get the model class name
        $modelName = class_basename(static::class);
        
        // Check multiple possible factory locations
        $possibleFactories = [
            "Database\\Generators\\{$modelName}Factory",
        ];

        foreach ($possibleFactories as $factoryClass) {
            if (class_exists($factoryClass)) {
                return new $factoryClass();
            }
        }

        throw new \RuntimeException(
            "Unable to locate factory for [" . static::class . "]. " .
            "Please create a factory class at Database\\Generators\\{$modelName}Factory"
        );
    }
}