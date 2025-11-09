<?php

namespace Core\Console\Commands;

use Core\Console\Command;
use Core\Database\Schema\MigrationRunner;

class MigrateRollbackCommand extends Command
{
    public function execute(array $args): int
    {
        $steps = isset($args[0]) ? (int) $args[0] : 1;
        $runner = new MigrationRunner($this->getMigrationsPath());
        
        $this->output->writeLine("Rolling back migrations...");
        $rolledBack = $runner->rollback($steps);
        
        if (empty($rolledBack)) {
            $this->output->writeLine("Nothing to rollback.");
        } else {
            $this->output->writeLine("Rolled back:");
            foreach ($rolledBack as $migration) {
                $this->output->success("  {$migration}");
            }
        }
        
        return 0;
    }
}