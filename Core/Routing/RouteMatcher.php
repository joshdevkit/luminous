<?php

namespace Core\Routing;


class RouteMatcher
{
    public function match(string $method, string $path, array $routes): ?array
    {
        $route = $this->findExactMatch($method, $path, $routes);

        if ($route !== null) {
            return $route;
        }

        // Check if path exists with different method
        $allowedMethods = $this->getAllowedMethodsForPath($path, $routes);

        if (!empty($allowedMethods)) {
            return $this->createMethodNotAllowedRoute($method, $path, $allowedMethods);
        }

        return $this->createNotFoundRoute($path);
    }

    private function findExactMatch(string $method, string $path, array $routes): ?array
    {
        foreach ($routes as $route) {
            if ($route['method'] === $method && $this->matchUri($route['uri'], $path, $params)) {
                $route['params'] = $params;
                return $route;
            }
        }

        return null;
    }

    private function getAllowedMethodsForPath(string $path, array $routes): array
    {
        $allowedMethods = [];

        foreach ($routes as $route) {
            if ($this->matchUri($route['uri'], $path, $params)) {
                $allowedMethods[] = $route['method'];
            }
        }

        return array_unique($allowedMethods);
    }

    private function createMethodNotAllowedRoute(string $method, string $path, array $allowedMethods): array
    {
        return [
            'type' => 'method_not_allowed',
            'method' => $method,
            'path' => $path,
            'allowed_methods' => $allowedMethods,
            'params' => [],
        ];
    }

    private function createNotFoundRoute(string $path): array
    {
        return [
            'type' => 'not_found',
            'path' => $path,
            'params' => [],
        ];
    }

    private function matchUri(string $pattern, string $uri, &$params = []): bool
    {
        $params = [];

        if ($pattern === $uri) {
            return true;
        }

        $regex = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/',
            fn($m) => '(?P<' . $m[1] . '>[^/]+)',
            $pattern
        );

        $regex = '#^' . $regex . '$#';

        if (preg_match($regex, $uri, $matches)) {
            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            return true;
        }

        return false;
    }
}