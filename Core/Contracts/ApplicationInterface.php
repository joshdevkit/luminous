<?php

namespace Core\Contracts;

use Core\Contracts\Container\ContainerInterface;
use Core\Contracts\Http\RequestInterface;
use Core\Contracts\Http\ResponseInterface;

interface ApplicationInterface
{
    /**
     * Get the container instance
     */
    public function getContainer(): ContainerInterface;

    /**
     * Get the base path of the application
     */
    public function basePath(string $path = ''): string;

    /**
     * Boot the application
     */
    public function boot(): void;

    /**
     * Handle an incoming request
     */
    public function handle(RequestInterface $request): ResponseInterface;

    /**
     * Register a service provider
     */
    public function register(ServiceProviderInterface $provider): void;

    /**
     * Determine if the application has been bootstrapped
     */
    public function hasBeenBootstrapped(): bool;
}