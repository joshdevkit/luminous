<?php

namespace Core\Contracts\Session;

interface SessionInterface
{
    /**
     * Start the session
     */
    public function start(): bool;

    /**
     * Get a session value
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Set a session value
     */
    public function put(string $key, mixed $value): void;

    /**
     * Check if a session key exists
     */
    public function has(string $key): bool;

    /**
     * Remove a session key
     */
    public function forget(string $key): void;

    /**
     * Get and remove a session value
     */
    public function pull(string $key, mixed $default = null): mixed;

    /**
     * Flash data for next request only
     */
    public function flash(string $key, mixed $value): void;

    /**
     * Get all session data
     */
    public function all(): array;

    /**
     * Regenerate session ID
     */
    public function regenerate(bool $deleteOldSession = true): bool;

    /**
     * Destroy the session
     */
    public function invalidate(): bool;

    /**
     * Get the session ID
     */
    public function getId(): string;

    /**
     * Set the session ID
     */
    public function setId(string $id): void;

    /**
     * Check if session is started
     */
    public function isStarted(): bool;
}