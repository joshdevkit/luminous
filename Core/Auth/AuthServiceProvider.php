<?php

namespace Core\Auth;

use Core\Http\Middlewares\AuthMiddleware;
use Core\Http\Middlewares\GuestMiddleware;
use Core\Providers\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register Auth Manager
        $this->app->singleton('auth', function ($c) {
            $session = $c->get('session');
            $config = $c->get('config');
            $entity = $config->get('auth.model', 'App\\Entities\\User');

            return new AuthManager($session, $entity);
        });

        // Register aliases for Auth Manager
        $this->app->alias('auth', AuthManager::class);
        $this->app->alias('auth', \Core\Contracts\Auth\AuthManagerContract::class);

        // Register Auth Middleware
        $this->app->bind('middleware.auth', function ($c) {
            $auth = $c->get('auth');
            $config = $c->get('config');
            $redirectTo = $config->get('auth.redirect.login', '/login');

            return new AuthMiddleware($auth, $redirectTo);
        });

        // Register alias for Auth Middleware
        $this->app->alias('middleware.auth', AuthMiddleware::class);

        // Register Guest Middleware
        $this->app->bind('middleware.guest', function ($c) {
            $auth = $c->get('auth');
            $config = $c->get('config');
            $redirectTo = $config->get('auth.redirect.home', '/dashboard');

            return new GuestMiddleware($auth, $redirectTo);
        });

        // Register alias for Guest Middleware
        $this->app->alias('middleware.guest', GuestMiddleware::class);
    }

    public function boot(): void
    {
        // Boot logic if needed
    }
}