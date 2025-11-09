<?php

namespace Core\Console\Commands;

use Core\Console\Command;

class DatabaseSeederCommand extends Command
{
    public function execute(array $args): int
    {
        // Default seeder class
        $seederClass = 'Database\\Dummy\\DatabaseSeeder';

        // Parse --class= flag
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--class=')) {
                
                $class = substr($arg, strlen('--class='));

                if (!str_contains($class, '\\')) {
                    $class = 'Database\\Dummy\\' . $class;
                }
                $seederClass = $class;
                break;
            }
        }

        if (!class_exists($seederClass)) {
            $this->output->error("Seeder class not found: {$seederClass}");
            return 1;
        }

        $this->output->info("Seeding database using [{$seederClass}]...");
        try {
            $seeder = new $seederClass();

            if (!method_exists($seeder, 'run')) {
                $this->output->error("Seeder must have a run() method.");
                return 1;
            }

            $startTime = microtime(true);
            $seeder->run();
            $endTime = microtime(true);

            $duration = round($endTime - $startTime, 2);

            $this->output->hr();
            $this->output->success("Database seeded successfully in {$duration}s");
            return 0;

        } catch (\Exception $e) {
            $this->output->error("Seeding failed: {$e->getMessage()}");
            $this->output->writeLine($e->getTraceAsString());
            return 1;
        }
    }
}
