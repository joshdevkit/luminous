<?php

namespace Core\Console\Commands;

use Core\Console\Command;
use Core\Database\Schema\MigrationRunner;

class MigrateCommand extends Command
{
    public function execute(array $args): int
    {
        $runner = new MigrationRunner($this->getMigrationsPath());
        
        $this->output->writeLine("Running migrations...");
        $ran = $runner->migrate();
        
        if (empty($ran)) {
            $this->output->writeLine("Nothing to migrate.");
        } else {
            $this->output->writeLine("Migrated:");
            foreach ($ran as $migration) {
                $this->output->success("  {$migration}");
            }
        }
        
        return 0;
    }
}