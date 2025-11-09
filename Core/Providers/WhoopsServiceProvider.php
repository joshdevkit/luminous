<?php

namespace Core\Providers;

use Core\Exceptions\ProductionErrorRenderer;
use Whoops\Run;
use Whoops\Handler\PrettyPageHandler;

class WhoopsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Run::class, function () {
            $whoops = new Run();
            $isApi = $this->isApiRequest();

            if ($isApi) {
                $handler = new \Core\Exceptions\JsonApiHandler();
                $handler->setJsonApi(true);
            } else {
                $server = $this->app->get('server');

                $handler = new PrettyPageHandler();
                $handler->setPageTitle("Oops! An error occurred");
                $handler->setEditor('phpstorm');

                // Application info
                $handler->addDataTable('Application', [
                    'Base Path' => $this->app->get('app')->basePath(),
                    'Environment' => env('APP_ENV', 'production'),
                    'Debug Mode' => env('APP_DEBUG', false) ? 'Enabled' : 'Disabled',
                ]);

                // Request info (via Server class)
                $handler->addDataTable('Request', [
                    'Method'        => $server->get('REQUEST_METHOD', 'Unknown'),
                    'Path'          => $server->requestUri(),
                    'Query String'  => $server->get('QUERY_STRING', ''),
                    'Remote Address' => $server->get('REMOTE_ADDR', 'Unknown'),
                    'Accept'        => $server->accept(),
                    'Content-Type'  => $server->contentType(),
                ]);
            }

            $whoops->pushHandler($handler);
            return $whoops;
        });
    }


    public function boot(): void
    {
        $debug = filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN);

        if ($debug) {
            $whoops = $this->app->make(Run::class);
            $whoops->register();
        } else {
            set_exception_handler([$this, 'handleProductionError']);
        }
    }

    public function handleProductionError(\Throwable $e): void
    {
        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, "Error: " . $e->getMessage() . PHP_EOL);
            fwrite(STDERR, $e->getTraceAsString() . PHP_EOL);
            exit(1);
        }
        ProductionErrorRenderer::render();
    }

    protected function isApiRequest(): bool
    {
        return $this->app->get('server')->isApiRequest();
    }
}
