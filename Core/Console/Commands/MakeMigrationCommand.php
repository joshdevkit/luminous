<?php

namespace Core\Console\Commands;

use Core\Console\Command;
use Core\Database\Schema\MigrationCreator;

class MakeMigrationCommand extends Command
{
    public function execute(array $args): int
    {
        if (empty($args[0])) {
            $this->output->error("Migration name is required.");
            $this->output->writeLine("Usage: php dev make:migration create_users_table");
            return 1;
        }

        $name = $args[0];

        $creator = new MigrationCreator($this->getMigrationsPath());

        // Check if already exists
        if ($creator->exists($name)) {
            $this->output->error("Migration already exists: {$name}");
            return 1;
        }

        $filename = $creator->create($name);

        $this->output->success("Created migration: {$filename}");
        return 0;
    }
}
