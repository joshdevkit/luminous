<?php

namespace Core\RateLimit;

use Core\Contracts\ServiceProviderInterface;
use Core\Contracts\Container\ContainerInterface;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\RateLimiter\Storage\CacheStorage;

class RateLimitServiceProvider implements ServiceProviderInterface
{
    protected ContainerInterface $container;

    public function setContainer(ContainerInterface $container): void
    {
        $this->container = $container;
    }

    public function register(): void
    {
        // Register the rate limiter storage
        $this->container->singleton('rate_limiter.storage', function ($c) {
            $config = $c->get('config')->get('rate_limit', []);
            
            // Use Redis if configured
            if (isset($config['storage']) && $config['storage'] === 'redis') {
                return $this->createRedisStorage($config);
            }
            
            // Fallback to in-memory storage (for development)
            return new CacheStorage(new ArrayAdapter());
        });
    }

    public function boot(): void
    {
        // Boot logic if needed
    }

    /**
     * Create Redis-backed storage for rate limiting
     */
    protected function createRedisStorage(array $config): CacheStorage
    {
        $redisConfig = $config['redis'] ?? [];
        
        $dsn = sprintf(
            'redis://%s:%s',
            $redisConfig['host'] ?? '127.0.0.1',
            $redisConfig['port'] ?? 6379
        );
        
        if (isset($redisConfig['password'])) {
            $dsn = sprintf(
                'redis://:%s@%s:%s',
                $redisConfig['password'],
                $redisConfig['host'] ?? '127.0.0.1',
                $redisConfig['port'] ?? 6379
            );
        }
        
        $redis = RedisAdapter::createConnection($dsn);
        $adapter = new RedisAdapter($redis, $redisConfig['namespace'] ?? 'rate_limit');
        
        return new CacheStorage($adapter);
    }
}