<?php

namespace Core\Contracts;

use Core\Contracts\Container\ContainerInterface;

interface ServiceProviderInterface
{
    /**
     * Register services in the container
     */
    public function register(): void;

    /**
     * Boot services (called after all providers are registered)
     */
    public function boot(): void;

    /**
     * Set the container instance
     */
    public function setContainer(ContainerInterface $container): void;
}