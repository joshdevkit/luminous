<?php

namespace Core\Http\Middlewares;

use Carbon\CarbonInterval;
use Closure;
use Core\Contracts\Http\RequestInterface;
use Core\Contracts\Http\ResponseInterface;
use Core\Contracts\Http\Middleware;
use Core\Contracts\Cache\CacheInterface;
use Core\Http\Response;

class RateLimitMiddleware implements Middleware
{
    /**
     * The cache instance.
     *
     * @var CacheInterface
     */
    protected CacheInterface $cache;

    /**
     * Maximum number of requests allowed.
     *
     * @var int
     */
    protected int $_maxAttempts = 60;

    /**
     * Time window in minutes.
     *
     * @var int
     */
    protected int $_decayMinutes = 1;

    /**
     * Time window in seconds.
     *
     * @var int
     */
    protected int $_decaySeconds = 60;

    /**
     * Custom key prefix for rate limiting.
     *
     * @var string
     */
    protected string $prefix;

    /**
     * Whether to use per-user rate limiting (requires authentication).
     *
     * @var bool
     */
    protected bool $perUser;

    /**
     * Magic getter for properties
     */
    public function __get(string $name): mixed
    {
        if ($name === 'maxAttempts') return $this->_maxAttempts;
        if ($name === 'decayMinutes') return $this->_decayMinutes;
        if ($name === 'decaySeconds') return $this->_decaySeconds;
        throw new \RuntimeException("Property {$name} does not exist");
    }

    /**
     * Magic setter to keep decayMinutes and decaySeconds in sync
     */
    public function __set(string $name, mixed $value): void
    {
        if ($name === 'maxAttempts') {
            $this->_maxAttempts = $value;
            return;
        }
        if ($name === 'decayMinutes') {
            $this->_decayMinutes = $value;
            $this->_decaySeconds = $value * 60;
            return;
        }
        if ($name === 'decaySeconds') {
            $this->_decaySeconds = $value;
            $this->_decayMinutes = (int) ($value / 60);
            return;
        }
        throw new \RuntimeException("Property {$name} cannot be set");
    }

    /**
     * RateLimitMiddleware constructor.
     */
    public function __construct(
        int|array $maxAttemptsOrConfig = 60,
        int $decaySeconds = 60,
        string $prefix = 'general',
        bool $perUser = false
    ) {
        // Get cache instance from container
        // Use ->store() to get the CacheInterface implementation
        $this->cache = app('cache')->store();

        if (is_array($maxAttemptsOrConfig)) {
            $config = $maxAttemptsOrConfig;

            if (isset($config[0])) {
                $this->_maxAttempts = $config[0] ?? 60;
                $this->_decaySeconds = $config[1] ?? 60;
                $this->_decayMinutes = (int) ($this->_decaySeconds / 60);
                $this->prefix = $config[2] ?? 'general';
                $this->perUser = $config[3] ?? false;
            } else {
                $this->_maxAttempts = $config['maxAttempts'] ?? $config['max_attempts'] ?? 60;

                if (isset($config['decayMinutes'])) {
                    $this->_decayMinutes = $config['decayMinutes'];
                    $this->_decaySeconds = $config['decayMinutes'] * 60;
                } elseif (isset($config['decay_minutes'])) {
                    $this->_decayMinutes = $config['decay_minutes'];
                    $this->_decaySeconds = $config['decay_minutes'] * 60;
                } else {
                    $this->_decaySeconds = $config['decaySeconds'] ?? $config['decay_seconds'] ?? 60;
                    $this->_decayMinutes = (int) ($this->_decaySeconds / 60);
                }

                $this->prefix = $config['prefix'] ?? 'general';
                $this->perUser = $config['perUser'] ?? $config['per_user'] ?? false;
            }
        } else {
            $this->_maxAttempts = $maxAttemptsOrConfig;
            $this->_decaySeconds = $decaySeconds;
            $this->_decayMinutes = (int) ($decaySeconds / 60);
            $this->prefix = $prefix;
            $this->perUser = $perUser;
        }
    }

    /**
     * Handle an incoming request.
     */
    public function handle(RequestInterface $request, Closure $next): ResponseInterface
    {
        $key = $this->resolveRequestKey($request);

        // Check if too many attempts
        if ($this->tooManyAttempts($key)) {
            return $this->buildRateLimitResponse($key);
        }

        // Increment attempts
        $this->hit($key);

        // Process request
        $response = $next($request);

        // Add rate limit headers
        $this->addHeaders($response, $key);

        return $response;
    }

    /**
     * Determine if the key has been "accessed" too many times.
     *
     * @param string $key
     * @return bool
     */
    protected function tooManyAttempts(string $key): bool
    {
        if ($this->attempts($key) >= $this->_maxAttempts) {
            if ($this->cache->has($key . ':timer')) {
                return true;
            }

            $this->resetAttempts($key);
        }

        return false;
    }

    /**
     * Increment the counter for a given key for a given decay time.
     *
     * @param string $key
     * @return int
     */
    protected function hit(string $key): int
    {
        // Set the timer if it doesn't exist
        $this->cache->add(
            $key . ':timer',
            time() + $this->_decaySeconds,
            $this->_decaySeconds
        );

        // Try to add the key with initial value of 0
        $added = $this->cache->add($key, 0, $this->_decaySeconds);

        // Increment the counter
        $hits = (int) $this->cache->increment($key);

        // If we just added it and hits is 1, we're good
        // If not added and hits is 1, something went wrong, reset it
        if (!$added && $hits === 1) {
            $this->cache->put($key, 1, $this->_decaySeconds);
        }

        return $hits;
    }

    /**
     * Get the number of attempts for the given key.
     *
     * @param string $key
     * @return int
     */
    protected function attempts(string $key): int
    {
        return (int) $this->cache->get($key, 0);
    }

    /**
     * Reset the number of attempts for the given key.
     *
     * @param string $key
     * @return bool
     */
    protected function resetAttempts(string $key): bool
    {
        return $this->cache->forget($key);
    }

    /**
     * Get the number of seconds until the "key" is accessible again.
     *
     * @param string $key
     * @return int
     */
    protected function availableIn(string $key): int
    {
        $timer = $this->cache->get($key . ':timer');
        return max(0, $timer - time());
    }

    /**
     * Get the number of remaining attempts.
     *
     * @param string $key
     * @return int
     */
    protected function remainingAttempts(string $key): int
    {
        return max(0, $this->_maxAttempts - $this->attempts($key));
    }

    /**
     * Resolve the rate limit key for the request.
     *
     * @param RequestInterface $request
     * @return string
     */
    protected function resolveRequestKey(RequestInterface $request): string
    {
        if ($this->perUser && function_exists('auth') && auth()->check()) {
            return 'rate_limit:' . $this->prefix . ':user:' . auth()->id();
        }

        return 'rate_limit:' . $this->prefix . ':ip:' . sha1($request->ip());
    }

    /**
     * Add rate limit headers to the response.
     *
     * @param ResponseInterface $response
     * @param string $key
     * @return void
     */
    protected function addHeaders(ResponseInterface $response, string $key): void
    {
        $response->header('X-RateLimit-Limit', (string) $this->_maxAttempts);
        $response->header('X-RateLimit-Remaining', (string) $this->remainingAttempts($key));

        if ($this->cache->has($key . ':timer')) {
            $resetTime = $this->cache->get($key . ':timer');
            $response->header('X-RateLimit-Reset', (string) $resetTime);
            $response->header('Retry-After', (string) max(0, $resetTime - time()));
        }
    }

    /**
     * Build the rate limit exceeded response.
     *
     * @param string $key
     * @return ResponseInterface
     */
    protected function buildRateLimitResponse(string $key): ResponseInterface
    {
        $retryAfter = $this->availableIn($key);

        $message = 'Too many requests. Please try again later.';
        if ($retryAfter > 0) {
            $message .= " Retry after {$retryAfter} seconds.";
        }

        $request = request();

        if ($request->expectsJson() || $request->isJson() || $request->ajax()) {
            $response = response()->json([
                'error' => 'Too Many Requests',
                'message' => $message,
                'retry_after' => $retryAfter,
            ], 429);
        } else {
            $remaining = CarbonInterval::seconds($retryAfter)->cascade()->forHumans([
                'short' => true,   
                'parts' => 2,    
            ]);

            $response = Response::back()->withErrors(
                "Too many requests. Please try again in {$remaining}."
            );
        }


        $response->header('X-RateLimit-Limit', (string) $this->_maxAttempts);
        $response->header('X-RateLimit-Remaining', '0');
        $response->header('Retry-After', (string) $retryAfter);

        if ($this->cache->has($key . ':timer')) {
            $resetTime = $this->cache->get($key . ':timer');
            $response->header('X-RateLimit-Reset', (string) $resetTime);
        }

        return $response;
    }

    /**
     * Create a rate limiter for authenticated users.
     *
     * @param int $maxAttempts
     * @param int $decaySeconds
     * @return static
     */
    public static function perUser(int $maxAttempts = 100, int $decaySeconds = 60): static
    {
        return new static($maxAttempts, $decaySeconds, 'user', true);
    }
}
