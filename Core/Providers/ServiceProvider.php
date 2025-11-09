<?php

namespace Core\Providers;

use Core\Contracts\Container\ContainerInterface;
use Core\Contracts\Http\RequestInterface;
use Core\Contracts\ServiceProviderInterface;
use Core\Http\Request;

abstract class ServiceProvider implements ServiceProviderInterface
{
    protected ContainerInterface $app;

    public function setContainer(ContainerInterface $app): void
    {
        $this->app = $app;
    }

    public function register(): void
    {
        // 
    }

    public function boot(): void
    {
        //
    }

    public function provides(): array
    {
        return [];
    }
}