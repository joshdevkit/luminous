<?php

namespace Core\Framework;

use Core\Auth\AuthServiceProvider;
use Core\Contracts\ApplicationInterface;
use Core\Contracts\Http\RequestInterface;
use Core\Contracts\Http\ResponseInterface;
use Core\Contracts\ServiceProviderInterface;
use Core\Container\Container;
use Core\Database\DatabaseServiceProvider;
use Core\Exceptions\HttpErrorRenderer;
use Core\Hashing\HashServiceProvider;
use Core\Http\Middlewares\MiddlewareCollection;
use Core\Http\Middlewares\VerifyCsrfToken;
use Core\Http\Request;
use Core\Http\Server;
use Core\Providers\CacheServiceProvider;
use Core\Providers\SymfonyErrorHandlerServiceProvider;
use Core\Providers\WhoopsServiceProvider;
use Core\RateLimit\RateLimitServiceProvider;
use Core\Routing\Router;
use Core\Session\SessionServiceProvider;
use Core\Support\AliasLoader;
use Dotenv\Dotenv;

use function Core\Filesystem\join_paths;

class Application extends Container implements ApplicationInterface
{
    const VERSION = '1.0';
    public const SECURITY_ERROR = 'security_error';

    protected string $basePath;
    protected array $serviceProviders = [];
    protected array $bootstrapProviders = [];
    protected bool $booted = false;
    protected bool $bootstrapped = false;
    protected MiddlewareCollection $middleware;
    protected bool $authEnabled = false;
    protected static ?Application $instance = null;

    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, '\/');
        $this->middleware = new MiddlewareCollection();

        $this->registerBaseBindings();
        $this->registerBaseServiceProviders();
        $this->bootstrap();
    }

    protected function registerBaseBindings(): void
    {
        static::setInstance($this);

        $this->instance(ApplicationInterface::class, $this);
        $this->instance('path', $this->path());
        $this->instance('app', $this);
        $this->instance('container', $this);
        $this->instance('server', new Server($_SERVER));
    }

    protected function registerBaseServiceProviders(): void
    {
        $this->register(new SymfonyErrorHandlerServiceProvider());
        // $this->register(new WhoopsServiceProvider());

        $this->register(new DatabaseServiceProvider());
        $this->register(new CacheServiceProvider());
        $this->register(new RateLimitServiceProvider());
        $this->register(new SessionServiceProvider());
        $this->register(new AuthServiceProvider());
        $this->register(new HashServiceProvider());
    }

    public function bootstrap(): ApplicationInterface
    {
        $this->bind(RequestInterface::class, Request::class);
        $this->setAuthEnabled($this->authEnabled);

        $this->instance('appPath', $this->basePath . '/app');
        $this->instance('app_version', self::VERSION);

        $this->loadEnvironment();
        $this->registerCoreServices();
        $this->loadConfiguration();
        $this->loadBootstrapProviders();  // NEW: Load from bootstrap/providers.php
        $this->registerServiceProviders();
        $this->configureRouter();

        $this->boot();
        $this->AliasLoader();

        return $this;
    }

    protected function loadEnvironment(): void
    {
        if (!file_exists($this->basePath . '/.env')) {
            return;
        }

        $dotenv = Dotenv::createImmutable($this->basePath);
        $dotenv->load();
    }

    protected function registerCoreServices(): void
    {
        $this->singleton('config', fn() => new \Core\Config\Repository());
        $this->singleton('router', fn($c) => new Router($c));
        $this->singleton('request', fn() => Request::capture());
    }

    protected function loadConfiguration(): void
    {
        $config = $this->get('config');
        $configPath = $this->basePath . '/config';

        if (is_dir($configPath)) {
            $config->loadDirectory($configPath);
        }
    }

    /**
     * Load service providers from bootstrap/providers.php
     */
    protected function loadBootstrapProviders(): void
    {
        $providersFile = $this->basePath . '/bootstrap/providers.php';

        if (file_exists($providersFile)) {
            $providers = require $providersFile;
            
            if (is_array($providers)) {
                $this->bootstrapProviders = $providers;
            }
        }
    }

    /**
     * Register application service providers
     */
    protected function registerServiceProviders(): void
    {
        // Get framework providers
        $frameworkProviders = $this->getFrameworkProviders();
        
        // Merge with bootstrap providers
        $allProviders = array_merge($frameworkProviders, $this->bootstrapProviders);

        // Remove duplicates
        $allProviders = array_unique($allProviders);

        foreach ($allProviders as $providerClass) {
            if (!class_exists($providerClass)) {
                throw new \RuntimeException("Service provider [{$providerClass}] not found.");
            }
            
            $this->register(new $providerClass());
        }
    }

    /**
     * Get framework service providers
     *
     * @return string[]
     */
    protected function getFrameworkProviders(): array
    {
        $providers = [
            \Core\Session\SessionServiceProvider::class,
            \Core\View\ViewServiceProvider::class,
            \Core\Database\DatabaseServiceProvider::class,
        ];

        if ($this->authEnabled) {
            $providers[] = \Core\Auth\AuthServiceProvider::class;
        }

        return $providers;
    }

    protected function configureRouter(): void
    {
        $router = $this->get('router');
        $config = $this->get('config');

        $routingConfig = $config->get('routing', []);

        if (!($routingConfig['auto_discovery']['enabled'] ?? true)) {
            return;
        }

        $namespaces = $routingConfig['namespaces'] ?? $this->getDefaultControllerNamespaces();

        foreach ($namespaces as $namespace => $directory) {
            if (is_dir($directory)) {
                $router->addControllerNamespace($namespace, $directory);
            }
        }

        if ($routingConfig['auto_discovery']['eager'] ?? false) {
            $router->discoverRoutes();
        }
    }

    protected function getDefaultControllerNamespaces(): array
    {
        return [
            'App\\Controllers' => $this->basePath . '/app/Controllers',
        ];
    }

    public function getContainer(): static
    {
        return $this;
    }

    public function path(string $path = ''): string
    {
        $base = $this->has('appPath')
            ? $this->get('appPath')
            : $this->basePath('app');

        return $this->joinPaths($base, $path);
    }

    public function joinPaths($basePath, $path = ''): string
    {
        return join_paths($basePath, $path);
    }

    public function basePath($path = ''): string
    {
        return $this->joinPaths($this->basePath, $path);
    }

    public function register(ServiceProviderInterface $provider): void
    {
        $provider->setContainer($this);
        $provider->register();

        $this->serviceProviders[] = $provider;
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        foreach ($this->serviceProviders as $provider) {
            $provider->boot();
        }

        $this->booted = true;
    }

    public function AliasLoader()
    {
        $aliases = config('app.aliases', []);

        if (empty($aliases)) {
            $aliases = [
                'Auth' => \Core\Facades\Auth::class,
                'View' => \Core\Facades\View::class,
                'DB' => \Core\Facades\DB::class,
                'Hash' => \Core\Facades\Hash::class,
                'Str' => \Core\Support\Str::class,
            ];
        }

        $loader = AliasLoader::getInstance($aliases);
        $loader->register();
    }

    public function handle(RequestInterface $request): ResponseInterface
    {
        $this->validateAppKey();

        $handler = fn($req) => $this->make('router')->dispatch($req);

        foreach (array_reverse($this->middleware->all()) as $middlewareClass) {
            $middleware = $this->make($middlewareClass);
            $handler = fn($req) => $middleware->handle($req, $handler);
        }

        return $handler($request);
    }

    protected function validateAppKey(): void
    {
        if (!$this->has('config')) {
            return;
        }

        $config = $this->get('config');
        $key = $config->get('app.key');
        $cipher = $config->get('app.cipher', 'AES-256-CBC');

        if ($config->get('app.env') === 'production' || $config->get('app.validate_key', true)) {
            AppKeyValidator::validate($key, $cipher);  
        }
    }

    public function hasBeenBootstrapped(): bool
    {
        return $this->bootstrapped;
    }

    public static function init(): ?Application
    {
        return static::$instance;
    }

    protected static function setInstance(?Application $app): void
    {
        static::$instance = $app;
    }

    // Middleware methods
    public function addMiddleware(string $middleware, int $priority = 0): self
    {
        $this->middleware->add($middleware, $priority);
        return $this;
    }

    public function prependMiddleware(string $middleware): self
    {
        $this->middleware->prepend($middleware);
        return $this;
    }

    public function removeMiddleware(string $middleware): self
    {
        $this->middleware->remove($middleware);
        return $this;
    }

    public function replaceMiddleware(string $old, string $new): self
    {
        $this->middleware->replace($old, $new);
        return $this;
    }

    public function getMiddlewareCollection(): MiddlewareCollection
    {
        return $this->middleware;
    }

    public function getMiddleware(): array
    {
        return $this->middleware->all();
    }

    public function hasMiddleware(string $middleware): bool
    {
        return $this->middleware->contains($middleware);
    }

    // Auth methods
    public function isAuthEnabled(): bool
    {
        return $this->authEnabled;
    }

    public function setAuthEnabled(bool $enabled): void
    {
        $this->authEnabled = $enabled;
    }

    public function withMiddleware(array $middleware): self
    {
        $this->middleware->merge($middleware);
        return $this;
    }

    public function useAuthentication(): self
    {
        $this->authEnabled = true;
        $this->setAuthEnabled(true);
        return $this;
    }

    protected function validateSecurityConfiguration(): bool
    {
        $missingMiddleware = [];

        if (!$this->middleware->contains(VerifyCsrfToken::class)) {
            $missingMiddleware[] = VerifyCsrfToken::class;
        }

        if (!empty($missingMiddleware)) {
            $message = 'Critical security middleware is not configured. The application cannot start without proper security measures in place.';

            $details = [
                'missing_middleware' => implode(', ', $missingMiddleware),
                'solution' => 'Add the missing middleware to your bootstrap configuration using $app->withMiddleware([...])',
            ];

            $this->instance(self::SECURITY_ERROR, HttpErrorRenderer::renderSecurityError($message, $details));
            return false;
        }

        return true;
    }

    public function getApplicationVersion(): string
    {
        return self::VERSION;
    }

    /**
     * Get all registered service provider instances
     *
     * @return ServiceProviderInterface[]
     */
    public function getRegisteredProviders(): array
    {
        return $this->serviceProviders;
    }

    /**
     * Get the bootstrap providers from bootstrap/providers.php
     *
     * @return array
     */
    public function getBootstrapProviders(): array
    {
        return $this->bootstrapProviders;
    }

    public function run(): void
    {
        foreach ($this->middleware as $middleware) {
            $this->addMiddleware($middleware);
        }

        if (!$this->validateSecurityConfiguration()) {
            if ($this->has(self::SECURITY_ERROR)) {
                $errorResponse = $this->get(self::SECURITY_ERROR);
                $errorResponse->send();
                exit(1);
            }
        }

        $request = $this->get('request');
        $response = $this->handle($request);
        $response->send();
    }

    public function __get(string $name)
    {
        return $this->get($name);
    }
}