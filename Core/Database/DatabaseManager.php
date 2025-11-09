<?php

namespace Core\Database;

use Core\Contracts\Database\ConnectionInterface;
use Core\Contracts\Database\ConnectionResolverInterface;
use Core\Exceptions\DatabaseException;

class DatabaseManager implements ConnectionResolverInterface
{
    protected array $config;
    protected array $connections = [];
    protected array $runtimeConfigs = [];
    protected string $defaultConnection;

    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->defaultConnection = $config['default'] ?? 'mysql';
    }

    /**
     * Add a connection at runtime
     *
     * @param array $config Connection configuration
     * @param string $name Connection name
     * @return void
     */
    public function addConnection(array $config, string $name): void
    {
        // Store the config for lazy loading
        $this->runtimeConfigs[$name] = $config;
        
        // Remove existing connection if any (will be recreated on next use)
        if (isset($this->connections[$name])) {
            unset($this->connections[$name]);
        }
    }

    public function connection(?string $name = null): ConnectionInterface
    {
        $name = $name ?? $this->defaultConnection;

        if (!isset($this->connections[$name])) {
            $this->connections[$name] = $this->makeConnection($name);
        }

        return $this->connections[$name];
    }

    protected function makeConnection(string $name): ConnectionInterface
    {
        // First check runtime configs (takes priority)
        if (isset($this->runtimeConfigs[$name])) {
            $config = $this->runtimeConfigs[$name];
            return new Connection($config, $name);
        }

        // Then check config file
        if (isset($this->config['connections'][$name])) {
            $config = $this->config['connections'][$name];
            return new Connection($config, $name);
        }

        throw new DatabaseException("Database connection [{$name}] not configured.");
    }

    /**
     * Check if a connection configuration exists
     *
     * @param string $name
     * @return bool
     */
    public function hasConnection(string $name): bool
    {
        return isset($this->runtimeConfigs[$name]) 
            || isset($this->config['connections'][$name]);
    }

    /**
     * Remove a connection configuration
     *
     * @param string $name
     * @return void
     */
    public function removeConnection(string $name): void
    {
        unset($this->runtimeConfigs[$name]);
        unset($this->connections[$name]);
    }

    public function getDefaultConnection(): string
    {
        return $this->defaultConnection;
    }

    public function setDefaultConnection(string $name): void
    {
        $this->defaultConnection = $name;
    }

    public function purge(?string $name = null): void
    {
        $name = $name ?? $this->defaultConnection;
        unset($this->connections[$name]);
    }

    public function disconnect(?string $name = null): void
    {
        $this->purge($name);
    }

    public function reconnect(?string $name = null): ConnectionInterface
    {
        $this->disconnect($name);
        return $this->connection($name);
    }

    public function getConnections(): array
    {
        return $this->connections;
    }

    /**
     * Get all available connection names (both config and runtime)
     *
     * @return array
     */
    public function getAvailableConnections(): array
    {
        $configConnections = array_keys($this->config['connections'] ?? []);
        $runtimeConnections = array_keys($this->runtimeConfigs);
        
        return array_unique(array_merge($configConnections, $runtimeConnections));
    }

    public function __call(string $method, array $parameters)
    {
        return $this->connection()->$method(...$parameters);
    }
}