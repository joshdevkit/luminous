<?php

namespace Core\Facades;
/**
 * @method static string make(string $value, array $options = [])
 * @method static bool check(string $value, string $hashedValue, array $options = [])
 * @method static bool needsRehash(string $hashedValue, array $options = [])
 * @method static array info(string $hashedValue)
 * @method static \Core\Contracts\Hashing\HasherContract driver(string|null $driver = null)
 * @method static bool isHashed(string $value)
 * @method static string getDefaultDriver()
 * @method static void setDefaultDriver(string $driver)
 *
 * @see \Core\Hashing\HashManager
 */
class Hash extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'hash';
    }
}