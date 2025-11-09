<?php

namespace Core\Framework;

use Core\Contracts\ApplicationInterface;
use Core\Config\Repository as Config;
use Core\Contracts\Http\RequestInterface;
use Core\Contracts\ProgramInterface;
use Core\Exceptions\HttpErrorRenderer;
use Core\Http\Middlewares\MiddlewareCollection;
use Core\Http\Middlewares\VerifyCsrfToken;
use Core\Routing\Router;
use Core\Http\Request;
use Core\Support\AliasLoader;
use Dotenv\Dotenv;

class Program implements ProgramInterface
{
    /**
     * The main application instance.
     *
     * @var Application
     */
    protected Application $app;

    /**
     * The base path of the application.
     *
     * @var string
     */
    protected string $basePath;

    /**
     * Global middleware classes to be added to the application.
     *
     */
    protected MiddlewareCollection $middleware;

    /**
     * Indicates whether authentication should be enabled.
     *
     * @var bool
     */
    protected bool $useAuth = false;

    /**
     * Program constructor.
     *
     * @param string $basePath The base path of the application.
     */
    public function __construct(string $basePath)
    {
        $this->basePath = $basePath;
        $this->middleware = new \Core\Http\Middlewares\MiddlewareCollection();
        $this->bootstrap();
    }

    /**
     * Bootstrap the application and return the instance.
     *
     * @return ApplicationInterface
     */
    public function bootstrap(): ApplicationInterface
    {
        $this->app = new Application($this->basePath);
        $this->app->bind(RequestInterface::class, Request::class);
        // Set authentication state on the app instance
        $this->app->setAuthEnabled($this->useAuth);

        $this->app->instance('appPath', $this->basePath . '/app');
        $this->app->instance('app_version', $this->app::VERSION);

        $this->loadEnvironment();
        $this->registerCoreServices();
        $this->loadConfiguration();
        $this->registerServiceProviders();
        $this->configureRouter();

        $this->app->boot();

        // NOTE: Security validation is now done in run() after middleware is configured
        
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
        return $this->app;
    }

    /**
     * Load environment variables from the .env file.
     */
    protected function loadEnvironment(): void
    {
        if (!file_exists($this->basePath . '/.env')) {
            return;
        }

        $dotenv = Dotenv::createImmutable($this->basePath);
        $dotenv->load();
    }

    /**
     * Register core services like config, router, and request.
     */
    protected function registerCoreServices(): void
    {
        $container = $this->app->getContainer();

        $container->singleton('config', fn() => new Config());
        $container->singleton('router', fn($c) => new Router($c));
        $container->singleton('request', fn() => Request::capture());
    }

    /**
     * Load configuration files from the config directory.
     */
    protected function loadConfiguration(): void
    {
        $config = $this->app->getContainer()->get('config');
        $configPath = $this->basePath . '/config';

        if (is_dir($configPath)) {
            $config->loadDirectory($configPath);
        }
    }

    /**
     * Register application service providers.
     */
    protected function registerServiceProviders(): void
    {
        $providers = $this->getServiceProviders();

        foreach ($providers as $providerClass) {
            $this->app->register(new $providerClass());
        }
    }

    /**
     * Get the default set of service providers.
     *
     * @return string[]
     */
    protected function getServiceProviders(): array
    {
        $providers = [
            \Core\Session\SessionServiceProvider::class,
            \Core\View\ViewServiceProvider::class,
            \Core\Database\DatabaseServiceProvider::class,
        ];

        if ($this->useAuth) {
            $providers[] = \Core\Auth\AuthServiceProvider::class;
        }

        return $providers;
    }

    /**
     * Configure the router and auto-discover controller namespaces.
     */
    protected function configureRouter(): void
    {
        $router = $this->app->getContainer()->get('router');
        $config = $this->app->getContainer()->get('config');

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

    /**
     * Return default controller namespaces if none are configured.
     *
     * @return array<string, string>
     */
    protected function getDefaultControllerNamespaces(): array
    {
        return [
            'App\\Controllers' => $this->basePath . '/app/Controllers',
        ];
    }

    /**
     * Set middleware for the application.
     *
     * @param string[] $middleware
     * @return $this
     */
    public function withMiddleware(array $middleware): self
    {
        $this->middleware->merge($middleware);
        return $this;
    }

    /**
     * Enable authentication for the application.
     *
     * @return $this
     */
    public function useAuthentication(): self
    {
        $this->useAuth = true;
        $this->app->setAuthEnabled(true);
        return $this;
    }

    /**
     * Validate that required security middleware is enabled when booted.
     *
     * @throws \RuntimeException
     */
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
                'solution' => 'Add the missing middleware to your bootstrap configuration using $program->withMiddleware([...])',
                // 'documentation' => 'Please refer to the security documentation for proper configuration.',
                // 'current_middleware' => implode(', ', $this->middleware->all()), 
            ];

            $this->app->instance('security_error', HttpErrorRenderer::renderSecurityError($message, $details));
            return false;
        }
        
        return true;
    }

    /**
     * Get the current application version.
     *
     * @return string
     */
    public function getApplicationVersion(): string
    {
        return $this->app::VERSION;
    }

    /**
     * Run the application, dispatching the HTTP request and sending the response.
     */
    public function run(): void
    {
        // Add middleware to application
        foreach ($this->middleware as $middleware) {
            $this->app->addMiddleware($middleware);
        }

        if (!$this->validateSecurityConfiguration()) {
            if ($this->app->has('security_error')) {
                $errorResponse = $this->app->get('security_error');
                $errorResponse->send();
                exit(1);
            }
        }

        // Normal request handling
        $request = $this->app->getContainer()->get('request');
        $response = $this->app->handle($request);
        $response->send();
    }
}