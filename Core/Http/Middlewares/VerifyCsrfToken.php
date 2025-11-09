<?php

namespace Core\Http\Middlewares;

use Core\Contracts\Http\RequestInterface;
use Core\Http\Response;

class VerifyCsrfToken
{
    /**
     * URIs that should be excluded from CSRF verification
     * 
     * @var array
     */
    protected array $except = [
        // Add routes to exclude, e.g., 'api/*', 'webhooks/*'
    ];

    /**
     * Handle an incoming request
     * 
     * @param RequestInterface $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle(RequestInterface $request, \Closure $next): mixed
    {
        // Ensure session is started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Generate token if it doesn't exist
        if (!isset($_SESSION['_token'])) {
            $_SESSION['_token'] = bin2hex(random_bytes(32));
        }

        $method = request()->getMethod();
        // dd($method);

        // Skip CSRF check for safe methods
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'])) {
            return $next($request);
        }

        // Skip CSRF check for excluded routes
        if ($this->inExceptArray($request)) {
            return $next($request);
        }

        // Verify CSRF token
        if (!$this->tokensMatch($request)) {
            return $this->handleTokenMismatch();
        }

        return $next($request);
    }

    /**
     * Determine if the request has a URI that should be excluded
     * 
     * @param RequestInterface $request
     * @return bool
     */
    protected function inExceptArray(RequestInterface $request): bool
    {
        $uri = $request->getUri();

        foreach ($this->except as $except) {
            // Convert wildcards to regex
            $pattern = str_replace('*', '.*', $except);

            if (preg_match('#^' . $pattern . '$#', $uri)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if the session and input CSRF tokens match
     * 
     * @param RequestInterface $request
     * @return bool
     */
    protected function tokensMatch(RequestInterface $request): bool
    {
        $token = $this->getTokenFromRequest($request);
        $sessionToken = $_SESSION['_token'] ?? null;

        if (!$token || !$sessionToken) {
            return false;
        }

        // Use hash_equals to prevent timing attacks
        return hash_equals($sessionToken, $token);
    }

    /**
     * Get the CSRF token from the request
     * 
     * @param RequestInterface $request
     * @return string|null
     */
    protected function getTokenFromRequest(RequestInterface $request): ?string
    {
        // 1. Try POST/request body first (most common for forms)
        if (isset($_POST['_token']) && !empty($_POST['_token'])) {
            return $_POST['_token'];
        }

        // 2. Try request input method
        $token = $request->input('_token');
        if ($token) {
            return $token;
        }

        // 3. Try headers for AJAX requests
        $headers = [
            'HTTP_X_CSRF_TOKEN',
            'HTTP_X_XSRF_TOKEN',
        ];

        foreach ($headers as $header) {
            if (isset($_SERVER[$header]) && !empty($_SERVER[$header])) {
                return $_SERVER[$header];
            }
        }

        return null;
    }

    /**
     * Handle a token mismatch exception
     * 
     * @return Response
     */
    protected function handleTokenMismatch(): Response
    {
        // Check if it's an AJAX request
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

        if ($isAjax) {
            return Response::json([
                'message' => 'CSRF token mismatch.',
                'error' => 'TokenMismatchException'
            ], 419);
        }

        // For regular requests, redirect back with error
        return Response::back()
            ->withError('error', 'Your session has expired. Please try again.')
            ->with('csrf_error', true);
    }

    /**
     * Regenerate the CSRF token
     * Call this after login/logout
     * 
     * @return string
     */
    public static function regenerateToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION['_token'] = bin2hex(random_bytes(32));

        return $_SESSION['_token'];
    }
}
