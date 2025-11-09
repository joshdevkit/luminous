<?php

namespace Core\Routing;

use Core\Routing\Attributes\Auth;
use Core\Routing\Attributes\Guest;
use Core\Routing\Attributes\Middleware;
use Core\Routing\Attributes\Throttle;
use Core\Routing\Attributes\Verified;
use ReflectionClass;
use ReflectionMethod;
use ReflectionAttribute;

class MiddlewareResolver
{
    /**
     * Built-in middleware aliases
     */
    private array $aliases = [
        'auth' => \Core\Http\Middlewares\AuthMiddleware::class,
        'guest' => \Core\Http\Middlewares\GuestMiddleware::class,
        'verified' => \Core\Http\Middlewares\VerifiedMiddleware::class,
        'throttle' => \Core\Http\Middlewares\RateLimitMiddleware::class
    ];

    /**
     * Attribute to middleware class mapping
     */
    private array $attributeMap = [
        Auth::class => \Core\Http\Middlewares\AuthMiddleware::class,
        Guest::class => \Core\Http\Middlewares\GuestMiddleware::class,
        Verified::class => \Core\Http\Middlewares\VerifiedMiddleware::class,
        Throttle::class => \Core\Http\Middlewares\RateLimitMiddleware::class,
    ];

    public function resolve(string $controllerClass, string $method): array
    {
        $middlewares = [];

        // 1. Get middleware from attributes (class-level and method-level)
        $attributeMiddlewares = $this->resolveFromAttributes($controllerClass, $method);
        $middlewares = array_merge($middlewares, $attributeMiddlewares);

        // 2. Get middleware from config file (for backward compatibility)
        $configMiddlewares = $this->resolveFromConfig($controllerClass, $method);
        $middlewares = array_merge($middlewares, $configMiddlewares);

        // Remove duplicates and return flat array of middleware class names
        return array_values(array_unique($middlewares, SORT_REGULAR));
    }

    /**
     * Resolve middleware from PHP attributes
     */
    private function resolveFromAttributes(string $controllerClass, string $method): array
    {
        $middlewares = [];

        try {
            $reflectionClass = new ReflectionClass($controllerClass);

            // Get class-level attributes
            $classMiddlewares = $this->getAttributeMiddlewares($reflectionClass);
            $middlewares = array_merge($middlewares, $classMiddlewares);

            // Get method-level attributes
            if ($reflectionClass->hasMethod($method)) {
                $reflectionMethod = $reflectionClass->getMethod($method);
                $methodMiddlewares = $this->getAttributeMiddlewares($reflectionMethod);
                $middlewares = array_merge($middlewares, $methodMiddlewares);
            }
        } catch (\ReflectionException $e) {
            // Controller class not found, skip attribute resolution
        }

        return $middlewares;
    }

    /**
     * Extract middleware from reflection attributes
     */
    private function getAttributeMiddlewares(ReflectionClass|ReflectionMethod $reflection): array
    {
        $middlewares = [];
        $attributes = $reflection->getAttributes();

        foreach ($attributes as $attribute) {
            $attributeName = $attribute->getName();
            $instance = $attribute->newInstance();

            // Handle generic Middleware attribute
            if ($attributeName === Middleware::class) {
                foreach ($instance->getMiddlewares() as $middleware) {
                    $middlewares[] = $this->resolveMiddleware($middleware);
                }
                continue;
            }

            // Handle Throttle attribute with parameters
            if ($attributeName === Throttle::class) {
                $middlewares[] = [
                    $this->attributeMap[$attributeName],
                    'handle',
                    [
                        'maxAttempts' => $instance->maxAttempts,
                        'decayMinutes' => $instance->decayMinutes
                    ]
                ];
                continue;
            }

            // Handle shorthand attributes (Auth, Guest, Verified)
            if (isset($this->attributeMap[$attributeName])) {
                $middlewares[] = $this->attributeMap[$attributeName];
            }
        }

        return $middlewares;
    }

    /**
     * Resolve middleware from config file (backward compatibility)
     */
    private function resolveFromConfig(string $controllerClass, string $method): array
    {
        $map = config('middleware', []);
        $middlewares = [];

        foreach ($map as $key => $value) {
            // Skip if value is not an array
            if (!is_array($value)) {
                continue;
            }

            // SPECIAL CASE: Handle 'group' key - iterate through all groups
            if ($key === 'group') {
                foreach ($value as $group) {
                    if (!is_array($group)) {
                        continue;
                    }

                    // Check if this group applies to the current controller
                    if (isset($group['controllers']) && isset($group['middleware'])) {
                        if (in_array($controllerClass, $group['controllers'], true)) {
                            foreach ($group['middleware'] as $middleware) {
                                $middlewares[] = $this->resolveMiddleware($middleware);
                            }
                        }
                    }
                }
                continue;
            }

            // Case 1: New unified format with 'middleware' key
            if (isset($value['middleware'])) {
                // Grouped controllers: has both 'controllers' and 'middleware'
                if (isset($value['controllers'])) {
                    if (in_array($controllerClass, $value['controllers'], true)) {
                        foreach ($value['middleware'] as $middleware) {
                            $middlewares[] = $this->resolveMiddleware($middleware);
                        }
                    }
                } 
                // Single controller with new format: key is controller class
                elseif ($key === $controllerClass) {
                    foreach ($value['middleware'] as $middleware) {
                        $middlewares[] = $this->resolveMiddleware($middleware);
                    }
                }
                continue;
            }

            // Case 2: Legacy format - Single controller class with array of middleware
            // Example: Controller::class => [Middleware::class, AnotherMiddleware::class]
            if ($key === $controllerClass) {
                foreach ($value as $middleware) {
                    $middlewares[] = $this->resolveMiddleware($middleware);
                }
                continue;
            }

            // Case 3: Specific method (exact match)
            // Example: 'Controller@method' => [Middleware::class]
            $methodKey = $controllerClass . '@' . $method;
            if ($key === $methodKey) {
                foreach ($value as $middleware) {
                    $middlewares[] = $this->resolveMiddleware($middleware);
                }
                continue;
            }

            // Case 4: Multiple methods syntax
            // Example: 'Controller@[method1,method2]' => [Middleware::class]
            if (is_string($key) && strpos($key, '@[') !== false) {
                if ($this->matchesMultipleMethods($key, $controllerClass, $method)) {
                    foreach ($value as $middleware) {
                        $middlewares[] = $this->resolveMiddleware($middleware);
                    }
                }
            }
        }

        return $middlewares;
    }

    /**
     * Resolve middleware alias or [Class, method] format to proper format
     * 
     * @param string|array $middleware Either an alias (e.g., 'auth'), full class name, or [Class, method]
     * @return string|array The resolved middleware
     */
    private function resolveMiddleware(string|array $middleware): string|array
    {
        // Handle array format [Class::class, 'method']
        if (is_array($middleware)) {
            return $middleware;
        }

        // If it's an alias, resolve it
        if (isset($this->aliases[$middleware])) {
            return $this->aliases[$middleware];
        }

        // Otherwise, assume it's already a full class name
        return $middleware;
    }

    /**
     * Register a custom middleware alias
     * 
     * @param string $alias The alias name
     * @param string $class The middleware class name
     * @return self
     */
    public function alias(string $alias, string $class): self
    {
        $this->aliases[$alias] = $class;
        return $this;
    }

    /**
     * Register a custom attribute mapping
     * 
     * @param string $attributeClass The attribute class name
     * @param string $middlewareClass The middleware class name
     * @return self
     */
    public function mapAttribute(string $attributeClass, string $middlewareClass): self
    {
        $this->attributeMap[$attributeClass] = $middlewareClass;
        return $this;
    }

    /**
     * Get all registered middleware aliases
     */
    public function getAliases(): array
    {
        return $this->aliases;
    }

    /**
     * Get all registered attribute mappings
     */
    public function getAttributeMappings(): array
    {
        return $this->attributeMap;
    }

    /**
     * Check if the current controller and method match a multiple methods pattern
     * 
     * Pattern: App\Controllers\AuthController@[login,register,forgot]
     */
    private function matchesMultipleMethods(string $pattern, string $controllerClass, string $method): bool
    {
        // Extract controller class from pattern
        $atPos = strpos($pattern, '@[');
        if ($atPos === false) {
            return false;
        }

        $patternController = substr($pattern, 0, $atPos);
        
        // Controller must match
        if ($patternController !== $controllerClass) {
            return false;
        }

        // Extract methods array
        $methodsStr = substr($pattern, $atPos + 2, -1); // Remove @[ and ]
        $methods = array_map('trim', explode(',', $methodsStr));

        return in_array($method, $methods, true);
    }
}