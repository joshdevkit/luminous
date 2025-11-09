<?php

namespace Core\Console\Commands;

use Core\Contracts\ApplicationInterface;
use Core\Console\OutputFormatter;

class KeyGenerateCommand
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
        // Check for --show flag
        $showKey = in_array('--show', $args) || in_array('-s', $args);
        
        // Check for --force flag
        $force = in_array('--force', $args) || in_array('-f', $args);

        $envFile = $this->basePath . '/.env';

        if (!file_exists($envFile)) {
            $this->output->error(".env file not found at {$envFile}");
            $this->output->info("Please create a .env file first by copying .env.example");
            return 1;
        }

        // Read current .env contents
        $envContents = file_get_contents($envFile);

        // Check if APP_KEY already exists and is not empty
        if (preg_match('/^APP_KEY=(.+)$/m', $envContents, $matches)) {
            $existingKey = trim($matches[1]);
            if (!empty($existingKey) && !$force) {
                $this->output->warning("Application key already exists!");
                $this->output->info("Use --force or -f flag to overwrite the existing key");
                return 0;
            }
        }

        // Generate a new key
        $key = $this->generateKey();

        // Update APP_KEY in .env
        if (preg_match('/^APP_KEY=.*$/m', $envContents)) {
            // Replace existing APP_KEY line
            $envContents = preg_replace('/^APP_KEY=.*$/m', "APP_KEY={$key}", $envContents);
        } else {
            // Add APP_KEY if it doesn't exist
            $envContents = rtrim($envContents) . "\nAPP_KEY={$key}\n";
        }

        // Write to .env file
        if (file_put_contents($envFile, $envContents) === false) {
            $this->output->error("Failed to write to .env file");
            return 1;
        }

        $this->output->success("Application key set successfully.");
        
        if ($showKey) {
            $this->output->info("Key: {$key}");
        }

        return 0;
    }

    /**
     * Generate a random encryption key.
     *
     * @return string
     */
    protected function generateKey(): string
    {
        return 'base64:' . base64_encode(random_bytes(32));
    }
}