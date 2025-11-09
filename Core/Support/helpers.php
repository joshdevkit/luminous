<?php

use Core\Contracts\Http\RequestInterface;
use Core\Framework\Application;
use Core\Support\Carbon;
use Core\Support\Env;
use Core\Support\HigherOrderTapProxy;

if (!function_exists('app')) {
    /**
     * Get the application instance or a resolved service.
     *
     * @param  string|null  $abstract
     * @param  array  $parameters
     * @return mixed
     */
    function app($abstract = null, array $parameters = []): mixed
    {
        $app = Application::init();

        if (is_null($app)) {
            throw new RuntimeException('Application instance has not been initialized.');
        }

        if (is_null($abstract)) {
            return $app;
        }

        return $app->getContainer()->make($abstract, $parameters);
    }
}

if (!function_exists('app_path')) {
    /**
     * Get the path to the "app" directory.
     *
     * @param  string  $path
     * @return string
     */
    function app_path(string $path = ''): string
    {
        /** @var Application $app */
        $app = app();

        return $app->path($path);
    }
}


if (!function_exists('env')) {
    /**
     * Get environment variable
     */
    function env(string $key, mixed $default = null): mixed
    {
        return Env::get($key, $default);
    }
}

if (!function_exists('config')) {
    /**
     * Get configuration value
     */
    function config(string $key, mixed $default = null): mixed
    {
        return app('config')->get($key, $default);
    }
}

if (!function_exists('base_path')) {
    /**
     * Get base path
     */
    function base_path(string $path = ''): string
    {
        return app()->basePath($path);
    }
}


if (!function_exists('database_path')) {
    /**
     * Get the path to the database directory.
     *
     * @param  string  $path
     * @return string
     */
    function database_path(string $path = ''): string
    {
        return base_path('database' . ($path ? DIRECTORY_SEPARATOR . $path : ''));
    }
}


if (!function_exists('response')) {
    /**
     * Create a new response instance
     */
    function response(mixed $content = '', int $status = 200, array $headers = []): \Core\Http\Response
    {
        return new \Core\Http\Response($content, $status, $headers);
    }
}

if (!function_exists('json')) {
    /**
     * Create a JSON response
     */
    function json(mixed $data, int $status = 200): \Core\Http\Response
    {
        return \Core\Http\Response::json($data, $status);
    }
}

if (!function_exists('redirect')) {
    /**
     * Create a redirect response.
     * 
     * If no URL is provided, it falls back to the HTTP_REFERER or '/'.
     */
    function redirect(?string $url = null, int $status = 302): \Core\Http\Response
    {
        return \Core\Http\Response::redirect($url, $status);
    }
}


if (!function_exists('back')) {
    function back(): \Core\Http\Response
    {
        return \Core\Http\Response::back();
    }
}


// if (!function_exists('db')) {
//     /**
//      * Get database manager instance
//      */
//     function db(?string $connection = null): \Core\Database\DatabaseManager|\Core\Contracts\Database\ConnectionInterface
//     {
//         $manager = app('db');

//         if ($connection) {
//             return $manager->connection($connection);
//         }

//         return $manager;
//     }
// }

// if (!function_exists('table')) {
//     /**
//      * Get query builder for a table
//      */
//     function table(string $table): \Core\Database\QueryBuilder
//     {
//         return db()->connection()->table($table);
//     }
// }

if (!function_exists('bcrypt')) {
    /**
     * Hash a password using the default Hash driver (e.g. BCRYPT).
     *
     * @param  string  $value
     * @param  array   $options
     * @return string
     */
    function bcrypt(string $value, array $options = []): string
    {
       return app('hash')->make($value, $options);
    }
}


if (!function_exists('now')) {
    /**
     * Get the current date and time as a Carbon instance.
     *
     * @param  string|null  $timezone
     * @return \Core\Support\Carbon
     */
    function now(?string $timezone = null): Carbon
    {
        $timezone = config('app.timezone', $timezone);
        return Carbon::now($timezone);
    }
}

if (!function_exists('view')) {
    /**
     * Create a view instance
     */
    function view(string $view, array $data = [])
    {
        return app('view')->make($view, $data);
    }
}


if (!function_exists('request')) {
    function request(): RequestInterface
    {
        return app()->make(RequestInterface::class);
    }
}

// if (!function_exists('old')) {
//     /**
//      * Retrieve an old input value from session flash data (like Laravel).
//      *
//      * @param string $key
//      * @param mixed $default
//      * @return mixed
//      */
//     function old(string $key, mixed $default = null): mixed
//     {
//         // Prefer session() helper if available
//         if (function_exists('session')) {
//             $session = session();

//             if ($session && $session->has('_old_input')) {
//                 $old = $session->get('_old_input');
//                 return $old[$key] ?? $default;
//             }
//         }

//         // Fallback to raw PHP session (if session() helper not booted yet)
//         return $_SESSION['_old_input'][$key] ?? $default;
//     }
// }

if (!function_exists('asset')) {
    /**
     * Generate asset URL
     */
    function asset(string $path): string
    {
        $baseUrl = getBaseUrl();
        return $baseUrl . '/' . ltrim($path, '/');
    }
}

if (!function_exists('url')) {
    /**
     * Generate URL
     */
    function url(string $path = ''): string
    {
        $baseUrl = getBaseUrl();
        return $baseUrl . '/' . ltrim($path, '/');
    }
}


if (!function_exists('router')) {
    /**
     * Generate a URL for a given controller and method (like Laravel's route()).
     *
     * @param string $controller Controller class name (without namespace)
     * @param string $method Controller method name
     * @param array $params Route parameters (for placeholders or query)
     *
     * @return string
     *
     * @throws InvalidArgumentException If route or parameters are invalid
     */
    function router(string $controller, string $method, array $params = []): string
    {
        /** @var \Core\Routing\Router $router */
        $router = app('router');
        $router->discoverRoutes();

        $routes = $router->getRoutes();

        // Normalize controller name
        $controller = trim($controller);
        if (!str_ends_with($controller, 'Controller')) {
            $controller .= 'Controller';
        }

        $targetRoute = null;
        foreach ($routes as $route) {
            if (!is_array($route['action'])) continue;

            [$routeController, $routeMethod] = $route['action'];

            if (
                (str_ends_with($routeController, $controller) ||
                 str_contains($routeController, $controller)) &&
                strtolower($routeMethod) === strtolower($method)
            ) {
                $targetRoute = $route;
                break;
            }
        }

        // 🚨 Throw if controller@method is not registered
        if (!$targetRoute) {
            throw new InvalidArgumentException(
                sprintf("No route found for [%s@%s].", $controller, $method)
            );
        }

        $uri = $targetRoute['uri'];

        // ✅ Validate and replace placeholders
        preg_match_all('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', $uri, $matches);
        $placeholders = $matches[1] ?? [];

        foreach ($placeholders as $key) {
            if (!array_key_exists($key, $params)) {
                throw new InvalidArgumentException(
                    "Missing required route parameter [{$key}] for [{$controller}@{$method}]."
                );
            }

            $uri = str_replace('{' . $key . '}', rawurlencode((string) $params[$key]), $uri);
            unset($params[$key]);
        }

        // Add query params for any leftover
        if (!empty($params)) {
            $uri .= (str_contains($uri, '?') ? '&' : '?') . http_build_query($params);
        }

        return $uri;
    }
}

if (! function_exists('tap')) {
    /**
     * Call the given Closure with the given value then return the value.
     *
     * @template TValue
     *
     * @param  TValue  $value
     * @param  (callable(TValue): mixed)|null  $callback
     * @return ($callback is null ? \Core\Support\HigherOrderTapProxy : TValue)
     */
    function tap($value, $callback = null)
    {
        if (is_null($callback)) {
            return new HigherOrderTapProxy($value);
        }

        $callback($value);

        return $value;
    }
}

if (!function_exists('getBaseUrl')) {
    /**
     * Get the base URL from the current request
     */
    function getBaseUrl(): string
    {
        // Try to get from config first
        $configUrl = config('app.url', null);

        if ($configUrl && $configUrl !== 'http://localhost') {
            return rtrim($configUrl, '/');
        }

        // Build from current request
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['SERVER_PORT'] ?? 80) == 443
            ? 'https'
            : 'http';

        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';

        return $protocol . '://' . $host;
    }
}

if (!function_exists('class_uses_recursive')) {
    /**
     * Get all traits used by a class, its parent classes and trait of traits.
     *
     * @param object|string $class
     * @return array
     */
    function class_uses_recursive($class): array
    {
        if (is_object($class)) {
            $class = get_class($class);
        }

        $results = [];

        foreach (array_reverse(class_parents($class)) + [$class => $class] as $class) {
            $results += trait_uses_recursive($class);
        }

        return array_unique($results);
    }
}

if (!function_exists('trait_uses_recursive')) {
    /**
     * Get all traits used by a trait and its traits.
     *
     * @param string $trait
     * @return array
     */
    function trait_uses_recursive(string $trait): array
    {
        $traits = class_uses($trait) ?: [];

        foreach ($traits as $trait) {
            $traits += trait_uses_recursive($trait);
        }

        return $traits;
    }
}

if (!function_exists('class_basename')) {
    /**
     * Get the class "basename" of the given object / class.
     *
     * @param string|object $class
     * @return string
     */
    function class_basename($class): string
    {
        $class = is_object($class) ? get_class($class) : $class;

        return basename(str_replace('\\', '/', $class));
    }
}

if (!function_exists('class_basename')) {
    function class_basename($class)
    {
        // If it's an object, get its class name
        if (is_object($class)) {
            $class = get_class($class);
        }

        // Handle namespaces and return the last part
        return basename(str_replace('\\', '/', $class));
    }
}


if (!function_exists('back')) {
    /**
     * Redirect back to previous page
     */
    function back(): \Core\Http\Response
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '/';
        return redirect($referer);
    }
}


if (!function_exists('db')) {
    /**
     * Get database manager instance or specific connection
     */
    function db(?string $connection = null): \Core\Database\DatabaseManager|\Core\Contracts\Database\ConnectionInterface
    {
        $manager = app('db');

        if ($connection) {
            return $manager->connection($connection);
        }

        return $manager;
    }
}

if (!function_exists('table')) {
    /**
     * Get query builder for a table
     */
    function table(string $table): \Core\Database\QueryBuilder
    {
        return db()->connection()->table($table);
    }
}

if (!function_exists('str')) {
    /**
     * Get Str helper instance or perform string operation
     */
    function str(?string $value = null): \Core\Support\Str|string
    {
        if ($value === null) {
            return new \Core\Support\Str();
        }

        return $value;
    }
}


if (!function_exists('auth')) {
    
    function auth()
    {
        return app('auth');
    }
}

if (!function_exists('user')) {
    function user(): ?object
    {
        return auth()->user();
    }
}
