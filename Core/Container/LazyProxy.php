<?php

namespace Core\Container;

/**
 * Lazy proxy for deferred instantiation
 */
class LazyProxy
{
    protected Container $container;
    protected string $abstract;
    protected mixed $instance = null;

    public function __construct(Container $container, string $abstract)
    {
        $this->container = $container;
        $this->abstract = $abstract;
    }

    protected function getInstance(): mixed
    {
        if ($this->instance === null) {
            $this->instance = $this->container->make($this->abstract);
        }
        return $this->instance;
    }

    public function __call(string $method, array $args): mixed
    {
        return $this->getInstance()->$method(...$args);
    }

    public function __get(string $name): mixed
    {
        return $this->getInstance()->$name;
    }

    public function __set(string $name, mixed $value): void
    {
        $this->getInstance()->$name = $value;
    }
}
