<?php

namespace Core\Routing;

use Core\Contracts\Http\RequestInterface;
use Core\Contracts\Http\ResponseInterface;
use Core\Contracts\Http\RouterInterface;
use Core\Contracts\Container\ContainerInterface;

class Router implements RouterInterface
{
    private array $routes = [];
    private ContainerInterface $container;
    private ?RequestInterface $currentRequest = null;
    private RouteDiscovery $routeDiscovery;
    private RouteDispatcher $routeDispatcher;
    private RouteMatcher $routeMatcher;
    private array $authRoutes = [];

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->routeDiscovery = new RouteDiscovery($this);
        $this->routeDispatcher = new RouteDispatcher($container);
        $this->routeMatcher = new RouteMatcher();
    }

    public function addControllerNamespace(string $namespace, string $directory): self
    {
        $this->routeDiscovery->addControllerNamespace($namespace, $directory);
        return $this;
    }

    public function discoverRoutes(): self
    {
        $this->routeDiscovery->discover();
        return $this;
    }

    public function registerController(string $controllerClass): self
    {
        $registrar = new ControllerRegistrar($this);
        $registrar->register($controllerClass);
        return $this;
    }

    public function get(string $uri, mixed $action): self
    {
        return $this->addRoute('GET', $uri, $action);
    }

    public function post(string $uri, mixed $action): self
    {
        return $this->addRoute('POST', $uri, $action);
    }

    public function put(string $uri, mixed $action): self
    {
        return $this->addRoute('PUT', $uri, $action);
    }

    public function delete(string $uri, mixed $action): self
    {
        return $this->addRoute('DELETE', $uri, $action);
    }

    public function patch(string $uri, mixed $action): self
    {
        return $this->addRoute('PATCH', $uri, $action);
    }

    public function addRoute(string $method, string $uri, mixed $action): self
    {
        $uri = '/' . trim($uri, '/');

        $this->routes[] = [
            'method' => strtoupper($method),
            'uri' => $uri,
            'action' => $action,
        ];

        return $this;
    }

    public function dispatch(RequestInterface $request): ResponseInterface
    {
        if (!$this->routeDiscovery->isDiscovered()) {
            $this->routeDiscovery->discover();
        }

        $this->currentRequest = $request;
        $this->container->instance(RequestInterface::class, $request);

        $route = $this->routeMatcher->match(
            $request->getMethod(),
            $request->getPath(),
            $this->routes
        );

        return $this->routeDispatcher->dispatch($route, $request);
    }

    public function getRoutes(): array
    {
        return $this->routes;
    }


    public function setAuthRoute(string $type, string $path): void
    {
        $this->authRoutes[$type] = $path;
    }

    public function authRoute(string $type = 'login'): ?string
    {
        return $this->authRoutes[$type] ?? null;
    }
}
