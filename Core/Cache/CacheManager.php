<?php

namespace Core\Cache;

use Core\Contracts\Cache\CacheInterface;
use Core\Contracts\Container\ContainerInterface;

class CacheManager
{
    /**
     * The application instance.
     *
     * @var ContainerInterface
     */
    protected ContainerInterface $container;

    /**
     * The array of resolved cache stores.
     *
     * @var array
     */
    protected array $stores = [];

    /**
     * The cache configuration.
     *
     * @var array
     */
    protected array $config;

    /**
     * Create a new Cache manager instance.
     *
     * @param ContainerInterface $container
     * @param array $config
     */
    public function __construct(ContainerInterface $container, array $config = [])
    {
        $this->container = $container;
        $this->config = $config;
    }

    /**
     * Get a cache store instance.
     *
     * @param string|null $name
     * @return CacheInterface
     */
    public function store(?string $name = null): CacheInterface
    {
        $name = $name ?: $this->getDefaultDriver();

        return $this->stores[$name] = $this->get($name);
    }

    /**
     * Get a cache driver instance.
     *
     * @param string $driver
     * @return CacheInterface
     */
    protected function get(string $driver): CacheInterface
    {
        return $this->stores[$driver] ?? $this->resolve($driver);
    }

    /**
     * Resolve the given store.
     *
     * @param string $name
     * @return CacheInterface
     * @throws \InvalidArgumentException
     */
    protected function resolve(string $name): CacheInterface
    {
        $config = $this->getConfig($name);

        if (is_null($config)) {
            throw new \InvalidArgumentException("Cache store [{$name}] is not defined.");
        }

        $driverMethod = 'create' . ucfirst($config['driver']) . 'Driver';

        if (method_exists($this, $driverMethod)) {
            return $this->{$driverMethod}($config);
        }

        throw new \InvalidArgumentException("Driver [{$config['driver']}] is not supported.");
    }

    /**
     * Create an instance of the file cache driver.
     *
     * @param array $config
     * @return CacheInterface
     */
    protected function createFileDriver(array $config): CacheInterface
    {
        $store = new FileStore(
            $config['path'],
            $config['prefix'] ?? ''
        );

        return new CacheRepository($store, $config['ttl'] ?? 3600);
    }

    /**
     * Create an instance of the APCu cache driver.
     *
     * @param array $config
     * @return CacheInterface
     */
    protected function createApcuDriver(array $config): CacheInterface
    {
        $store = new ApcuStore($config['prefix'] ?? '');

        return new CacheRepository($store, $config['ttl'] ?? 3600);
    }

    /**
     * Create an instance of the array cache driver.
     *
     * @param array $config
     * @return CacheInterface
     */
    protected function createArrayDriver(array $config): CacheInterface
    {
        $store = new ArrayStore($config['prefix'] ?? '');

        return new CacheRepository($store, $config['ttl'] ?? 3600);
    }

    /**
     * Get the cache connection configuration.
     *
     * @param string $name
     * @return array|null
     */
    protected function getConfig(string $name): ?array
    {
        return $this->config['stores'][$name] ?? null;
    }

    /**
     * Get the default cache driver name.
     *
     * @return string
     */
    public function getDefaultDriver(): string
    {
        return $this->config['default'] ?? 'file';
    }

    /**
     * Set the default cache driver name.
     *
     * @param string $name
     * @return void
     */
    public function setDefaultDriver(string $name): void
    {
        $this->config['default'] = $name;
    }

    /**
     * Dynamically call the default driver instance.
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->store()->$method(...$parameters);
    }
}