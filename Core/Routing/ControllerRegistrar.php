<?php

namespace Core\Routing;

use Core\Routing\Attributes\ApiRoute;

class ControllerRegistrar
{
    private Router $router;
    private RouteAttributeResolver $attributeResolver;
    private ConventionRouter $conventionRouter;

    public function __construct(Router $router)
    {
        $this->router = $router;
        $this->attributeResolver = new RouteAttributeResolver();
        $this->conventionRouter = new ConventionRouter();
    }

    public function register(string $controllerClass): void
    {
        if (!class_exists($controllerClass)) {
            return;
        }

        $reflection = new \ReflectionClass($controllerClass);

        if ($reflection->isAbstract() || $reflection->isInterface()) {
            return;
        }

        $classPrefix = $this->getClassPrefix($reflection);

        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if (str_starts_with($method->getName(), '__')) {
                continue;
            }

            $this->registerMethodRoutes($controllerClass, $method, $classPrefix);
        }
    }

    private function getClassPrefix(\ReflectionClass $reflection): string
    {
        $attributes = $reflection->getAttributes(ApiRoute::class);

        if (empty($attributes)) {
            return '';
        }

        $instance = $attributes[0]->newInstance();
        return $instance->prefix;
    }

    private function registerMethodRoutes(
        string $controller,
        \ReflectionMethod $method,
        string $classPrefix = ''
    ): void {
        $routeAttributes = $this->attributeResolver->resolveFromMethod($method);

        if (!empty($routeAttributes)) {
            foreach ($routeAttributes as $routeData) {
                $fullPath = $this->applyPrefix($routeData['path'], $classPrefix);
                 $authAttr = $method->getAttributes(\Core\Routing\Attributes\AuthRoute::class);
                if (!empty($authAttr)) {
                    $instance = $authAttr[0]->newInstance();
                    $this->router->setAuthRoute($instance->type, $fullPath);
                }
                $this->router->addRoute(
                    $routeData['method'],
                    $fullPath,
                    [$controller, $method->getName()]
                );
            }
        } elseif ($this->shouldAutoRoute($method)) {
            $route = $this->conventionRouter->generateRoute($controller, $method, $classPrefix);
            $authAttr = $method->getAttributes(\Core\Routing\Attributes\AuthRoute::class);
        if (!empty($authAttr)) {
            $instance = $authAttr[0]->newInstance();
            $this->router->setAuthRoute($instance->type, $route['path']);
        }
            $this->router->addRoute($route['method'], $route['path'], $route['action']);
        }
    }

    private function applyPrefix(string $path, string $prefix): string
    {
        if (empty($prefix) || $prefix === '/') {
            return $path;
        }

        $prefix = '/' . trim($prefix, '/');
        $path = '/' . trim($path, '/');

        if (str_starts_with($path, $prefix)) {
            return $path;
        }

        return $prefix . $path;
    }

    private function shouldAutoRoute(\ReflectionMethod $method): bool
    {
        $declaringClass = $method->getDeclaringClass()->getName();
        return !str_ends_with($declaringClass, '\\Controller');
    }
}