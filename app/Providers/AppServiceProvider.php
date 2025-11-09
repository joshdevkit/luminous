<?php

namespace App\Providers;

use App\Repositories\CartServiceRepository;
use App\Repositories\ProductServiceRepository;
use App\Repositories\UserRepository;
use App\RepositoryInterface\UserRepositoryInterface;
use App\ServiceInterface\UserServiceInterface;
use App\Services\CartService;
use App\Services\ProductService;
use App\Services\UserService;
use Core\Providers\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services
     */
    public function register(): void
    {
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(UserServiceInterface::class, UserService::class);


        /**
         * Cart and Product services
         */
        $this->app->bind(ProductServiceRepository::class, ProductService::class);
        $this->app->bind(CartServiceRepository::class, CartService::class);
    }

    /**
     * Bootstrap any application services
     */
    public function boot(): void
    {
        // Perform any bootstrapping logic here
        // Example: Register event listeners, middleware, etc.
    }
}
