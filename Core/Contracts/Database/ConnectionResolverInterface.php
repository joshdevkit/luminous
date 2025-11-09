<?php

namespace Core\Contracts\Database;

interface ConnectionResolverInterface
{
    /**
     * Get a database connection instance.
     *
     * @param string|null $name
     * @return ConnectionInterface
     */
    public function connection(?string $name = null): ConnectionInterface;

    /**
     * Get the default connection name.
     *
     * @return string
     */
    public function getDefaultConnection(): string;

    /**
     * Set the default connection name.
     *
     * @param string $name
     * @return void
     */
    public function setDefaultConnection(string $name): void;

    /**
     * Add a connection at runtime.
     *
     * @param array $config
     * @param string $name
     * @return void
     */
    public function addConnection(array $config, string $name): void;

    /**
     * Check if a connection configuration exists.
     *
     * @param string $name
     * @return bool
     */
    public function hasConnection(string $name): bool;

    /**
     * Remove a connection configuration.
     *
     * @param string $name
     * @return void
     */
    public function removeConnection(string $name): void;

    /**
     * Get all active connections.
     *
     * @return array
     */
    public function getConnections(): array;

    /**
     * Disconnect and remove a connection.
     *
     * @param string|null $name
     * @return void
     */
    public function disconnect(?string $name = null): void;

    /**
     * Reconnect to a given database.
     *
     * @param string|null $name
     * @return ConnectionInterface
     */
    public function reconnect(?string $name = null): ConnectionInterface;

    /**
     * Purge a connection instance.
     *
     * @param string|null $name
     * @return void
     */
    public function purge(?string $name = null): void;
}