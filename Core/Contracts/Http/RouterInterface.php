<?php

namespace Core\Contracts\Http;

interface RouterInterface
{
    /**
     * Register a GET route
     */
    public function get(string $uri, mixed $action): self;

    /**
     * Register a POST route
     */
    public function post(string $uri, mixed $action): self;

    /**
     * Register a PUT route
     */
    public function put(string $uri, mixed $action): self;

    /**
     * Register a DELETE route
     */
    public function delete(string $uri, mixed $action): self;

    /**
     * Register a PATCH route
     */
    public function patch(string $uri, mixed $action): self;

    /**
     * Add a namespace for automatic controller discovery
     */
    public function addControllerNamespace(string $namespace, string $directory): self;

    /**
     * Register routes from a specific controller class
     */
    public function registerController(string $controllerClass): self;

    /**
     * Discover and register all routes from configured namespaces
     */
    public function discoverRoutes(): self;

    /**
     * Dispatch the request to the matched route
     */
    public function dispatch(RequestInterface $request): ResponseInterface;

    /**
     * Get all registered routes (useful for debugging/CLI tools)
     */
    public function getRoutes(): array;
}