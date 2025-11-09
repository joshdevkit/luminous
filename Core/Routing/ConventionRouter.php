<?php

namespace Core\Routing;

class ConventionRouter
{
    public function generateRoute(
        string $controller,
        \ReflectionMethod $method,
        string $classPrefix = ''
    ): array {
        $methodName = $method->getName();
        $httpMethod = $this->guessHttpMethod($methodName);

        if (!empty($classPrefix) && $classPrefix !== '/') {
            $path = $this->generatePathWithPrefix($methodName, $method);
        } else {
            $controllerName = $this->getControllerBaseName($controller);
            $path = $this->generatePathWithController($controllerName, $methodName, $method);
        }

        $fullPath = $this->applyPrefix($path, $classPrefix);

        return [
            'method' => $httpMethod,
            'path' => $fullPath,
            'action' => [$controller, $methodName],
        ];
    }

    private function generatePathWithPrefix(string $method, \ReflectionMethod $reflection): string
    {
        $parts = [];

        if ($method !== 'index') {
            $parts[] = $method;
        }

        foreach ($reflection->getParameters() as $param) {
            if ($param->getType() && !$param->getType()->isBuiltin()) {
                continue;
            }
            $parts[] = '{' . $param->getName() . '}';
        }

        return empty($parts) ? '/' : '/' . implode('/', $parts);
    }

    private function generatePathWithController(
        string $controller,
        string $method,
        \ReflectionMethod $reflection
    ): string {
        $parts = [$controller];

        if ($method !== 'index') {
            $parts[] = $method;
        }

        foreach ($reflection->getParameters() as $param) {
            if ($param->getType() && !$param->getType()->isBuiltin()) {
                continue;
            }
            $parts[] = '{' . $param->getName() . '}';
        }

        return '/' . implode('/', $parts);
    }

    private function getControllerBaseName(string $controller): string
    {
        $parts = explode('\\', $controller);
        $name = end($parts);
        return strtolower(str_replace('Controller', '', $name));
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

    private function guessHttpMethod(string $methodName): string
    {
        $prefix = strtolower(substr($methodName, 0, 6));

        return match (true) {
            str_starts_with($prefix, 'index'),
            str_starts_with($prefix, 'show'),
            str_starts_with($prefix, 'get'),
            str_starts_with($prefix, 'list') => 'GET',
            str_starts_with($prefix, 'store'),
            str_starts_with($prefix, 'create'),
            str_starts_with($prefix, 'post') => 'POST',
            str_starts_with($prefix, 'update'),
            str_starts_with($prefix, 'put') => 'PUT',
            str_starts_with($prefix, 'delete'),
            str_starts_with($prefix, 'remove') => 'DELETE',
            str_starts_with($prefix, 'patch') => 'PATCH',
            default => 'GET',
        };
    }
}