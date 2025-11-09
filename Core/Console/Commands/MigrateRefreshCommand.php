<?php

namespace Core\Console\Commands;

use Core\Console\Command;
use Core\Database\Schema\MigrationRunner;

class MigrateRefreshCommand extends Command
{
    public function execute(array $args): int
    {
        $runner = new MigrationRunner($this->getMigrationsPath());
        
        $this->output->writeLine("Refreshing migrations...");
        $ran = $runner->refresh();
        
        $this->output->writeLine("Refreshed:");
        foreach ($ran as $migration) {
            $this->output->success("  {$migration}");
        }
        
        return 0;
    }
}