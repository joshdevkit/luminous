<?php

declare(strict_types=1);

namespace Core\Support;

/**
 * Class alias loader for facades
 */
class AliasLoader
{
    /**
     * The array of class aliases.
     */
    protected array $aliases;

    /**
     * Indicates if a loader has been registered.
     */
    protected bool $registered = false;

    /**
     * The singleton instance.
     */
    protected static ?self $instance = null;

    /**
     * Create a new AliasLoader instance.
     */
    private function __construct(array $aliases)
    {
        $this->aliases = $aliases;
    }

    /**
     * Get or create the singleton alias loader instance.
     */
    public static function getInstance(array $aliases = []): self
    {
        if (is_null(static::$instance)) {
            static::$instance = new static($aliases);
        }

        return static::$instance;
    }

    /**
     * Load a class alias if it is registered.
     */
    public function load(string $alias): bool
    {
        if (isset($this->aliases[$alias])) {
            return class_alias($this->aliases[$alias], $alias);
        }

        return false;
    }

    /**
     * Add an alias to the loader.
     */
    public function alias(string $alias, string $class): void
    {
        $this->aliases[$alias] = $class;
    }

    /**
     * Register the loader on the auto-loader stack.
     */
    public function register(): void
    {
        if (!$this->registered) {
            $this->prependToLoaderStack();
            $this->registered = true;
        }
    }

    /**
     * Prepend the load method to the auto-loader stack.
     */
    protected function prependToLoaderStack(): void
    {
        spl_autoload_register([$this, 'load'], true, true);
    }

    /**
     * Get the registered aliases.
     */
    public function getAliases(): array
    {
        return $this->aliases;
    }

    /**
     * Set the registered aliases.
     */
    public function setAliases(array $aliases): void
    {
        $this->aliases = $aliases;
    }
}