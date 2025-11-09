<?php

namespace Core\Hashing;

use Core\Contracts\Hashing\HasherContract;
use InvalidArgumentException;

class HashManager implements HasherContract
{
    /**
     * The array of created "drivers".
     *
     * @var array
     */
    protected array $drivers = [];

    /**
     * The configuration array.
     *
     * @var array
     */
    protected array $config;

    /**
     * The default driver name.
     *
     * @var string
     */
    protected string $defaultDriver = 'bcrypt';

    /**
     * Create a new Hash manager instance.
     *
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->defaultDriver = $config['driver'] ?? 'bcrypt';
    }

    /**
     * Get a driver instance.
     *
     * @param string|null $driver
     * @return HasherContract
     */
    public function driver(?string $driver = null): HasherContract
    {
        $driver = $driver ?: $this->getDefaultDriver();

        if (!isset($this->drivers[$driver])) {
            $this->drivers[$driver] = $this->createDriver($driver);
        }

        return $this->drivers[$driver];
    }

    /**
     * Create a new driver instance.
     *
     * @param string $driver
     * @return HasherContract
     *
     * @throws InvalidArgumentException
     */
    protected function createDriver(string $driver): HasherContract
    {
        $config = $this->config[$driver] ?? [];

        return match ($driver) {
            'bcrypt' => new BcryptHasher($config),
            'argon', 'argon2', 'argon2id' => new ArgonHasher($config),
            default => throw new InvalidArgumentException("Driver [{$driver}] not supported."),
        };
    }

    /**
     * Hash the given value.
     *
     * @param string $value
     * @param array $options
     * @return string
     */
    public function make(string $value, array $options = []): string
    {
        return $this->driver()->make($value, $options);
    }

    /**
     * Check the given plain value against a hash.
     *
     * @param string $value
     * @param string $hashedValue
     * @param array $options
     * @return bool
     */
    public function check(string $value, string $hashedValue, array $options = []): bool
    {
        return $this->driver()->check($value, $hashedValue, $options);
    }

    /**
     * Check if the given hash has been hashed using the given options.
     *
     * @param string $hashedValue
     * @param array $options
     * @return bool
     */
    public function needsRehash(string $hashedValue, array $options = []): bool
    {
        return $this->driver()->needsRehash($hashedValue, $options);
    }

    /**
     * Get information about the given hashed value.
     *
     * @param string $hashedValue
     * @return array
     */
    public function info(string $hashedValue): array
    {
        return $this->driver()->info($hashedValue);
    }

    /**
     * Get the default driver name.
     *
     * @return string
     */
    public function getDefaultDriver(): string
    {
        return $this->defaultDriver;
    }

    /**
     * Set the default driver name.
     *
     * @param string $driver
     * @return void
     */
    public function setDefaultDriver(string $driver): void
    {
        $this->defaultDriver = $driver;
    }

    /**
     * Determine if a given string is already hashed.
     *
     * @param string $value
     * @return bool
     */
    public function isHashed(string $value): bool
    {
        return password_get_info($value)['algo'] !== null;
    }

    /**
     * Dynamically call the default driver instance.
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public function __call(string $method, array $parameters)
    {
        return $this->driver()->$method(...$parameters);
    }
}