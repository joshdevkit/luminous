<?php

namespace Core\Console;

use Core\Contracts\ApplicationInterface;

abstract class Command
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

    abstract public function execute(array $args): int;

    protected function getMigrationsPath(): string
    {
        return $this->basePath . '/database/migrations';
    }
}