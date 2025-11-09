<?php

namespace Core\Http\Middlewares;

use Core\Contracts\Http\Middleware;
use Core\Contracts\Http\RequestInterface;
use Core\Contracts\Http\ResponseInterface;
use Core\Exceptions\HttpErrorRenderer;

class CheckMaintenanceMode implements Middleware
{
    /**
     * Handle an incoming request.
     *
     * @param RequestInterface $request
     * @param callable $next
     * @return ResponseInterface
     */
    public function handle(RequestInterface $request, \Closure $next): ResponseInterface
    {
        $maintenanceEnabled = env('APP_MAINTENANCE', false);
        if ($maintenanceEnabled) {
            return HttpErrorRenderer::render(
                503,
                'Be right back! The application is currently under maintenance.'
            );
        }

        return $next($request);
    }
}
