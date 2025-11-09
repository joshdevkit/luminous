<?php

namespace Core\Console\Commands;

use Core\Console\Command;
use Core\Database\Schema\MigrationRunner;

class MigrateStatusCommand extends Command
{
    public function execute(array $args): int
    {
        $runner = new MigrationRunner($this->getMigrationsPath());
        $status = $runner->status();
        
        $this->output->writeLine("\nMigration Status:");
        $this->output->writeLine(str_repeat('-', 80));
        $this->output->writeLine(sprintf("%-60s %-10s %s", "Migration", "Status", "Batch"));
        $this->output->writeLine(str_repeat('-', 80));
        
        foreach ($status as $item) {
            $statusText = $item['ran'] ? '✓ Ran' : '✗ Pending';
            $batch = $item['batch'] ?? '-';
            $this->output->writeLine(sprintf("%-60s %-10s %s", $item['name'], $statusText, $batch));
        }
        
        $this->output->writeLine(str_repeat('-', 80));
        
        return 0;
    }
}