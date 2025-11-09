<?php

namespace Core\Console\Commands;

use Core\Console\Command;
use Core\Database\Schema\MigrationRunner;

class MigrateResetCommand extends Command
{
    public function execute(array $args): int
    {
        $runner = new MigrationRunner($this->getMigrationsPath());
        
        $this->output->writeLine("Resetting all migrations...");
        $rolledBack = $runner->reset();
        
        if (empty($rolledBack)) {
            $this->output->writeLine("Nothing to reset.");
        } else {
            $this->output->writeLine("Reset:");
            foreach ($rolledBack as $migration) {
                $this->output->success("  {$migration}");
            }
        }
        
        return 0;
    }
}