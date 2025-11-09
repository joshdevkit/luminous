<?php

namespace Core\Http\Middlewares;

use Closure;
use Core\Auth\AuthManager;
use Core\Contracts\Http\RequestInterface;
use Core\Contracts\Http\ResponseInterface;
use Core\Contracts\Http\Middleware;

/**
 * ---------------------------------------------------------------
 * Middleware: AuthMiddleware
 * ---------------------------------------------------------------
 *
 * This middleware ensures that a user is authenticated before
 * accessing protected routes. If the user is not authenticated,
 * they are redirected to the login page.
 *
 * @package Core\Auth
 */
class AuthMiddleware implements Middleware
{
    protected AuthManager $auth;
    protected string $redirectTo;

    /**
     * Create a new AuthMiddleware instance.
     *
     * @param  AuthManager  $auth
     * @param  string  $redirectTo
     */
    public function __construct(AuthManager $auth, string $redirectTo = '/login')
    {
        $this->auth = $auth;
        $this->redirectTo = $redirectTo;
    }

    /**
     * Handle an incoming request.
     *
     * @param  RequestInterface  $request
     * @param  Closure  $next
     * @return ResponseInterface
     */
    public function handle(RequestInterface $request, Closure $next): ResponseInterface
    {
        if ($this->auth->guest()) {
            if ($request->isMethod('get')) {
                session()->put('url.intended', $request->getUri());
            }

            return redirect(app('router')->authRoute('login') ?? $this->redirectTo);
        }

        return $next($request);
    }

}
