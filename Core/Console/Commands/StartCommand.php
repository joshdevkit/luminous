<?php

namespace Core\Console\Commands;

use Core\Contracts\ApplicationInterface;
use Core\Console\OutputFormatter;

class StartCommand
{
    protected ApplicationInterface $app;
    protected string $basePath;
    protected OutputFormatter $output;

    public function __construct(ApplicationInterface $app, string $basePath, OutputFormatter $output)
    {
        $this->app = $app;
        $this->basePath = $basePath;
        $this->output = $output;
    }

    public function execute(array $args): int
    {
        $appUrl = env('APP_URL');
        if (!$appUrl) {
            $this->output->warning("APP_URL not set in environment. Defaulting to localhost:8000.");
            $host = 'localhost';
        } else {
            $parsedUrl = parse_url($appUrl);
            $host = $parsedUrl['host'] ?? 'localhost';
        }

        $port = env('APP_PORT', 8000);
        $docroot = $this->basePath . '/public';  

        if (!is_dir($docroot)) {
            $this->output->error("Document root directory not found: {$docroot}");
            $this->output->info("Ensure your project has a 'public' directory or update the path in StartCommand.");
            return 1;
        }

        $this->output->info("Starting PHP development server on {$host}:{$port}...");
        $this->output->info("Press Ctrl+C to stop the server.");

        // Run the PHP built-in server
        $command = "php -S {$host}:{$port} -t {$docroot}";
        passthru($command);  // Runs the command and outputs directly to console

        return 0;
    }
}
