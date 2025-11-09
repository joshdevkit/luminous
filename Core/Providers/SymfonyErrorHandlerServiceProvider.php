<?php

namespace Core\Providers;

use Core\Exceptions\ProductionErrorRenderer;
use Symfony\Component\ErrorHandler\Debug;
use Symfony\Component\ErrorHandler\ErrorHandler;

class SymfonyErrorHandlerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $debug = filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN);

        if ($debug) {
            Debug::enable();
        } else {
             ErrorHandler::register();
            set_exception_handler(function (\Throwable $e) {
                if (PHP_SAPI === 'cli') {
                    fwrite(STDERR, "Error: " . $e->getMessage() . PHP_EOL);
                    fwrite(STDERR, $e->getTraceAsString() . PHP_EOL);
                    exit(1);
                }

                ProductionErrorRenderer::render();
            });
            ErrorHandler::register();
        }
    }
}
