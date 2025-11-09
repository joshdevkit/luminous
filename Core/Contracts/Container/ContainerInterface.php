<?php

namespace Core\Contracts\Container;

use Psr\Container\ContainerInterface as PsrContainerInterface;

interface ContainerInterface extends PsrContainerInterface
{
    /**
     * Bind a concrete implementation to an abstract
     */
    public function bind(string $abstract, mixed $concrete = null, bool $shared = false): void;

    /**
     * Bind a singleton to the container
     */
    public function singleton(string $abstract, mixed $concrete = null): void;

    /**
     * Bind a scoped service (per-request, per-job, etc.)
     */
    public function scoped(string $abstract, mixed $concrete = null): void;

    /**
     * Bind a primitive/scalar value
     */
    public function bindValue(string $name, mixed $value): void;

    /**
     * Resolve a dependency from the container
     */
    public function make(string $abstract, array $parameters = []): mixed;

    /**
     * Register an existing instance as shared
     */
    public function instance(string $abstract, mixed $instance): void;

    /**
     * Determine if the given abstract has been bound
     */
    public function bound(string $abstract): bool;

    /**
     * Alias a type to a different name
     */
    public function alias(string $abstract, string $alias): void;

    /**
     * Determine if a given string is an alias
     */
    public function isAlias(string $name): bool;

    /**
     * Get the alias for an abstract if available
     */
    public function getAlias(string $abstract): string;

    /**
     * Begin a new scope (e.g., request scope)
     */
    public function beginScope(string $scopeName): void;

    /**
     * End the current scope
     */
    public function endScope(): void;

    /**
     * Clear reflection cache
     */
    public function clearReflectionCache(): void;

    /**
     * Get current resolution stack (debugging)
     */
    public function getResolutionStack(): array;

    /**
     * Flush all container state
     */
    public function flush(): void;
}