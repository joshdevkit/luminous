<?php

namespace Core\Console;

use Core\Contracts\ApplicationInterface;

class Application
{
    protected ApplicationInterface $app;
    protected string $basePath;
    protected OutputFormatter $output;
    protected array $commands = [];

    public function __construct(ApplicationInterface $app, string $basePath)
    {
        $this->app = $app;
        $this->basePath = $basePath;
        $this->output = new OutputFormatter();
        $this->registerCommands();
    }

    protected function registerCommands(): void
    {
        $this->commands = [
            'make:migration' => new Commands\MakeMigrationCommand($this->app, $this->basePath, $this->output),
            'make:entity'    => new Commands\MakeEntityCommand($this->app, $this->basePath, $this->output),
            'make:controller' => new Commands\MakeControllerCommand($this->app, $this->basePath, $this->output),
            'make:factory'   => new Commands\MakeFactoryCommand($this->app, $this->basePath, $this->output),
            'make:seeder'    => new Commands\MakeSeederCommand($this->app, $this->basePath, $this->output),
            'migrate' => new Commands\MigrateCommand($this->app, $this->basePath, $this->output),
            'key:generate' => new Commands\KeyGenerateCommand($this->app, $this->basePath, $this->output),
            'make:alter' => new Commands\MakeAlterCommand($this->app, $this->basePath, $this->output),
            'db:seed' => new Commands\DatabaseSeederCommand($this->app, $this->basePath, $this->output),
            'migrate:rollback' => new Commands\MigrateRollbackCommand($this->app, $this->basePath, $this->output),
            'migrate:reset' => new Commands\MigrateResetCommand($this->app, $this->basePath, $this->output),
            'migrate:refresh' => new Commands\MigrateRefreshCommand($this->app, $this->basePath, $this->output),
            'migrate:status' => new Commands\MigrateStatusCommand($this->app, $this->basePath, $this->output),
            'key:generate' => new Commands\KeyGenerateCommand($this->app, $this->basePath, $this->output),
            'start' => new Commands\StartCommand($this->app, $this->basePath, $this->output),
        ];
    }

    public function run(array $argv): int
    {
        $commandName = $argv[1] ?? null;
        $args = array_slice($argv, 2);

        if (!$commandName) {
            $this->showHelp();
            return 0;
        }

        if (!$this->verifyDatabase()) {
            return 1;
        }

        return $this->executeCommand($commandName, $args);
    }

    protected function verifyDatabase(): bool
    {
        try {
            $this->app->getContainer()->get('db');
            return true;
        } catch (\Exception $e) {
            $this->output->error("Database not initialized!");
            $this->output->writeLine($e->getMessage());
            return false;
        }
    }

    protected function executeCommand(string $name, array $args): int
    {
        if (!isset($this->commands[$name])) {
            $this->output->error("Unknown command: {$name}");
            $this->output->writeLine("Run 'php dev' to see available commands.");
            return 1;
        }

        try {
            return $this->commands[$name]->execute($args);
        } catch (\Exception $e) {
            $this->output->error("Command failed: {$e->getMessage()}");
            return 1;
        }
    }

    protected function showHelp(): void
    {
        $this->output->writeLine("================================================================");
        $this->output->writeLine("  Available commands:");
        $this->output->writeLine("");
        $this->output->writeLine("  Server:");
        $this->output->writeLine("    start                     Start the development server");
        $this->output->writeLine("");
        $this->output->writeLine("  Generators:");
        $this->output->writeLine("    make:migration <name>     Create a new migration");
        $this->output->writeLine("    make:entity <name> [-m]   Create a new entity (with optional migration)");
        $this->output->writeLine("    make:controller <name>    Create a new controller");
        $this->output->writeLine("    make:factory <name>       Create a new factory");
        $this->output->writeLine("    make:seeder <name>        Create a new seeder");
        $this->output->writeLine("");
        $this->output->writeLine("  Database:");
        $this->output->writeLine("    migrate                   Run pending migrations");
        $this->output->writeLine("    migrate:rollback [steps]  Rollback migrations");
        $this->output->writeLine("    migrate:reset             Reset all migrations");
        $this->output->writeLine("    migrate:refresh           Reset and re-run all migrations");
        $this->output->writeLine("    migrate:status            Show migration status");
        $this->output->writeLine("    migrate:alter <name>      Create an alter migration");
        $this->output->writeLine("    db:seed [class]           Seed the database");
        $this->output->writeLine("");
        $this->output->writeLine("  Other:");
        $this->output->writeLine("    key:generate              Generate application key");
        $this->output->writeLine("================================================================");
    }
}
