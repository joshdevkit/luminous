<?php

namespace Core\Hashing;

use Core\Providers\ServiceProvider;
use Core\Contracts\Hashing\HasherContract;

class HashServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton('hash', function ($c) {
            $config = $c->get('config');
            
            // Get hashing configuration
            $hashConfig = [
                'driver' => $config->get('hashing.driver', 'bcrypt'),
                'bcrypt' => [
                    'rounds' => $config->get('hashing.bcrypt.rounds', 12),
                    'verify' => $config->get('hashing.bcrypt.verify', false),
                ],
                'argon' => [
                    'memory' => $config->get('hashing.argon.memory', 65536),
                    'time' => $config->get('hashing.argon.time', 4),
                    'threads' => $config->get('hashing.argon.threads', 1),
                    'verify' => $config->get('hashing.argon.verify', false),
                ],
            ];

            return new HashManager($hashConfig);
        });

        // Register aliases for convenience
        $this->app->alias('hash', HashManager::class);
        $this->app->alias('hash', HasherContract::class);
    }

    /**
     * Bootstrap the service provider.
     *
     * @return void
     */
    public function boot(): void
    {
        // Boot logic if needed
    }
}