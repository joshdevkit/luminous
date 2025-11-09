<?php

namespace Core\Container;


/**
 * Contextual binding builder
 */
class ContextualBindingBuilder
{
    protected Container $container;
    protected string $concrete;
    protected array $needs = [];

    public function __construct(Container $container, string $concrete)
    {
        $this->container = $container;
        $this->concrete = $concrete;
    }

    public function needs(string|array $abstract): self
    {
        $this->needs = is_array($abstract) ? $abstract : [$abstract];
        return $this;
    }

    public function give(mixed $implementation): void
    {
        foreach ($this->needs as $abstract) {
            $this->container->addContextualBinding($this->concrete, $abstract, $implementation);
        }
    }
}