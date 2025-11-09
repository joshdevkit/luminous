<?php

namespace Core\Routing;

use Core\Contracts\Container\ContainerInterface;
use Core\Contracts\Http\RequestInterface;

class RouteExecutor
{
    private ContainerInterface $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function execute(mixed $action, array $params, RequestInterface $request): mixed
    {
        if ($action instanceof \Closure) {
            return $this->executeClosure($action, $params, $request);
        }

        if (is_string($action) && str_contains($action, '@')) {
            [$controller, $method] = explode('@', $action, 2);
            return $this->executeControllerMethod($controller, $method, $params, $request);
        }

        if (is_array($action) && count($action) === 2) {
            [$controller, $method] = $action;
            return $this->executeControllerMethod($controller, $method, $params, $request);
        }

        if (is_callable($action)) {
            return call_user_func_array($action, $params);
        }

        throw new \RuntimeException("Invalid route action");
    }

    private function executeClosure(\Closure $closure, array $params, RequestInterface $request): mixed
    {
        $reflection = new \ReflectionFunction($closure);
        $boundParams = $this->bindParameters($reflection, $params, $request);
        return $closure(...$boundParams);
    }

    private function executeControllerMethod(
        string|object $controller,
        string $method,
        array $params,
        RequestInterface $request
    ): mixed {
        if (is_string($controller)) {
            $controller = $this->container->make($controller);
        }

        if (method_exists($controller, 'setRequest')) {
            $controller->setRequest($request);
        }

        $reflection = new \ReflectionMethod($controller, $method);
        $boundParams = $this->bindParameters($reflection, $params, $request);

        return $controller->$method(...$boundParams);
    }

    private function bindParameters(
        \ReflectionFunctionAbstract $reflection,
        array $routeParams,
        RequestInterface $request
    ): array {
        $binder = new ParameterBinder($request, $this->container); // <-- pass container here
        return $binder->bindParameters($reflection, $routeParams);
    }
}
