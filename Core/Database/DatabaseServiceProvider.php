<?php

namespace Core\Database;

use Core\Contracts\Database\ConnectionResolverInterface;
use Core\Providers\ServiceProvider;

class DatabaseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('db', function ($c) {
            $config = $c->get('config')->get('database');
            return new DatabaseManager($config);
        });

        $this->app->singleton(ConnectionResolverInterface::class, function ($c) {
            return $c->get('db');
        });

        $this->app->singleton(DatabaseManager::class, function ($c) {
            return $c->get('db');
        });
        
    }

    public function boot(): void
    {
        $manager = $this->app->get('db');
        Capsule::setManager($manager);
    }
}