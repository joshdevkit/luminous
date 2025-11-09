<?php

namespace Core\Routing;

use Core\Contracts\Container\ContainerInterface;
use Core\Contracts\Http\RequestInterface;
use Core\Contracts\Http\ResponseInterface;
use Core\Exceptions\HttpErrorRenderer;
use Core\Http\Response;

class RouteDispatcher
{
    private ContainerInterface $container;
    private MiddlewareResolver $middlewareResolver;
    private RouteExecutor $routeExecutor;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->middlewareResolver = new MiddlewareResolver();
        $this->routeExecutor = new RouteExecutor($container);
    }

    public function dispatch(?array $route, RequestInterface $request): ResponseInterface
    {
        if (preg_match('#//+#', $request->getUri())) {
            return HttpErrorRenderer::render(400, 'Bad Request: Malformed URI', '');
        }
        
        if ($route === null) {
            return HttpErrorRenderer::render(404, '', $request->getPath());
        }

        if (isset($route['type'])) {
            return $this->handleSpecialRoute($route);
        }

        return $this->executeRoute($route, $request);
    }

    private function handleSpecialRoute(array $route): ResponseInterface
    {
        if ($route['type'] === 'method_not_allowed') {
            return $this->methodNotAllowedResponse(
                $route['method'],
                $route['path'],
                $route['allowed_methods']
            );
        }

        if ($route['type'] === 'not_found') {
            return HttpErrorRenderer::render(404, '', $route['path']);
        }

        return HttpErrorRenderer::render(500, 'Unknown route type', '');
    }

    private function methodNotAllowedResponse(
        string $requestedMethod,
        string $path,
        array $allowedMethods
    ): ResponseInterface {
        $allowedMethodsStr = implode(', ', $allowedMethods);

        $message = sprintf(
            'Method %s not allowed for route %s. Allowed methods: %s',
            $requestedMethod,
            $path,
            $allowedMethodsStr
        );

        $response = HttpErrorRenderer::render(405, $message, $path);

        if ($response instanceof Response) {
            $response->header('Allow', $allowedMethodsStr);
        }

        return $response;
    }

    private function executeRoute(array $route, RequestInterface $request): ResponseInterface
    {
        [$controller, $method] = $route['action'];

        $middlewares = $this->middlewareResolver->resolve($controller, $method);

        // Base handler executes the controller method
        $handler = fn($req) => $this->routeExecutor->execute(
            $route['action'],
            $route['params'],
            $req
        );

        // Build middleware stack (in reverse order)
        foreach (array_reverse($middlewares) as $middlewareDefinition) {
            $currentHandler = $handler;
            $handler = function ($req) use ($currentHandler, $middlewareDefinition) {
                // Wrap the next handler to ensure it returns ResponseInterface
                $next = fn($request) => $this->prepareResponse($currentHandler($request));

                $result = $this->executeMiddleware($middlewareDefinition, $req, $next);

                // Wrap middleware result in prepareResponse
                return $this->prepareResponse($result);
            };
        }

        // Execute middleware chain
        $result = $handler($request);

        return $this->prepareResponse($result);
    }

    /**
     * Execute a middleware with proper handling of different formats
     */
    private function executeMiddleware(string|array $middlewareDefinition, RequestInterface $request, callable $next): mixed
    {
        // DEBUG: Log middleware execution
        if (is_array($middlewareDefinition)) {
            error_log("Executing middleware: " . json_encode($middlewareDefinition));
        } else {
            error_log("Executing middleware: " . $middlewareDefinition);
        }

        // Handle array format with parameters: [class, method, params]
        if (
            is_array($middlewareDefinition)
            && count($middlewareDefinition) === 3
            && is_string($middlewareDefinition[0])
            && is_string($middlewareDefinition[1])
            && is_array($middlewareDefinition[2])
        ) {
            [$class, $method, $params] = $middlewareDefinition;
            
            error_log("Creating middleware instance: {$class}");
            error_log("Parameters to set: " . json_encode($params));
            
            $instance = $this->container->make($class);

            // Set parameters on the middleware instance
            // Use try-catch to handle both public properties and magic setters
            foreach ($params as $key => $value) {
                try {
                    error_log("Setting {$key} = {$value} on middleware");
                    $instance->$key = $value;
                    error_log("Successfully set {$key}");
                } catch (\Throwable $e) {
                    error_log("WARNING: Could not set property {$key}: " . $e->getMessage());
                }
            }

            // Verify the values were set (use magic getter)
            try {
                if (isset($instance->maxAttempts)) {
                    error_log("Middleware maxAttempts after setting: " . $instance->maxAttempts);
                }
                if (isset($instance->decayMinutes)) {
                    error_log("Middleware decayMinutes after setting: " . $instance->decayMinutes);
                }
                if (isset($instance->decaySeconds)) {
                    error_log("Middleware decaySeconds after setting: " . $instance->decaySeconds);
                }
            } catch (\Throwable $e) {
                error_log("Could not read middleware properties: " . $e->getMessage());
            }

            // Call the middleware method
            return $instance->$method($request, $next);
        }

        // Handle array format without parameters: [class, method]
        if (
            is_array($middlewareDefinition)
            && count($middlewareDefinition) === 2
            && is_string($middlewareDefinition[0])
            && is_string($middlewareDefinition[1])
        ) {
            [$class, $method] = $middlewareDefinition;
            $instance = $this->container->make($class);

            // Call custom middleware method (e.g., alreadyAuthenticated)
            return $instance->$method($request, $next);
        }

        // Standard middleware class with handle() method
        $instance = $this->container->make($middlewareDefinition);
        return $instance->handle($request, $next);
    }

    private function prepareResponse(mixed $result): ResponseInterface
    {
        if ($result instanceof ResponseInterface) {
            return $result;
        }

        if ($result instanceof \Core\Contracts\View\ViewInterface) {
            return new Response($result);
        }

        if (is_array($result) || is_object($result)) {
            return Response::json($result);
        }

        return new Response($result);
    }
}