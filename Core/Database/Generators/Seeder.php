<?php

namespace Core\Database\Generators;

use Core\Console\OutputFormatter;

abstract class Seeder
{
    protected OutputFormatter $output;

    public function __construct()
    {
        $this->output = new OutputFormatter();
    }

    /**
     * Run the database seeds.
     */
    abstract public function run(): void;

    /**
     * Call another seeder class.
     */
    protected function call(string|array $seederClasses): void
    {
        $classes = is_array($seederClasses) ? $seederClasses : [$seederClasses];

        foreach ($classes as $class) {
            $className = class_basename($class);
            
            if (!class_exists($class)) {
                $this->output->error("Seeder class not found: {$class}");
                continue;
            }

            $this->output->info("Seeding {$className}...");
            
            $seeder = new $class();
            
            if (!method_exists($seeder, 'run')) {
                $this->output->error("Seeder {$className} must have a run() method.");
                continue;
            }

            $startTime = microtime(true);
            $seeder->run();
            $endTime = microtime(true);
            
            $duration = round($endTime - $startTime, 2);
            $this->output->success("Completed {$className} ({$duration}s)");
        }
    }

    /**
     * Output helper methods for convenience
     */
    protected function info(string $message): void
    {
        $this->output->info($message);
    }

    protected function success(string $message): void
    {
        $this->output->success($message);
    }

    protected function error(string $message): void
    {
        $this->output->error($message);
    }

    protected function warning(string $message): void
    {
        $this->output->warning($message);
    }

    protected function line(string $message = ''): void
    {
        $this->output->line($message);
    }
}