<?php

namespace Core\Http\Middlewares;

use Core\Contracts\Http\RequestInterface;
use Core\Contracts\Http\ResponseInterface;
use Core\Contracts\Http\Middleware;
use Core\Http\RedirectResponse;

class VerifiedMiddleware implements Middleware
{
    public function handle(RequestInterface $request, \Closure $next): ResponseInterface
    {
        $user = $request->user();

        if (!$user) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'error' => 'Unauthenticated',
                    'message' => 'Login to continue.'
                ], 401);
            }
           return new RedirectResponse(app('router')->authRoute('login'));
        }

        if ($user->email_verified_at === null) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'error' => 'Email verification required',
                    'message' => 'Please verify your account first.'
                ], 403);
            }

            return (new RedirectResponse(app('router')->authRoute('verify')))
                ->withInfo('Please verify your email address to continue.');
        }

        return $next($request);
    }
}