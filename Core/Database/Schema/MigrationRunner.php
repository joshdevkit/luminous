<?php

namespace Core\Database\Schema;

use Core\Database\Capsule;
use DirectoryIterator;

/**
 * Migration Runner - Handles executing migrations
 */
class MigrationRunner
{
    protected string $migrationsPath;
    protected string $migrationsTable = 'migrations';

    public function __construct(string $migrationsPath)
    {
        $this->migrationsPath = rtrim($migrationsPath, '/');
        $this->ensureMigrationsTableExists();
    }

    /**
     * Run all pending migrations
     */
    public function migrate(): array
    {
        $migrations = $this->getPendingMigrations();
        
        if (empty($migrations)) {
            return [];
        }

        $ran = [];
        $batch = $this->getNextBatchNumber();

        foreach ($migrations as $migration) {
            $this->runMigration($migration, $batch);
            $ran[] = $migration['name'];
        }

        return $ran;
    }

    /**
     * Rollback the last batch of migrations
     */
    public function rollback(int $steps = 1): array
    {
        $rolledBack = [];

        for ($i = 0; $i < $steps; $i++) {
            $batch = $this->getLastBatch();
            
            if (empty($batch)) {
                break;
            }

            foreach (array_reverse($batch) as $migration) {
                $this->rollbackMigration($migration);
                $rolledBack[] = $migration['name'];
            }
        }

        return $rolledBack;
    }

    /**
     * Reset all migrations
     */
    public function reset(): array
    {
        $migrations = $this->getRanMigrations();
        $rolledBack = [];

        foreach (array_reverse($migrations) as $migration) {
            $this->rollbackMigration($migration);
            $rolledBack[] = $migration['name'];
        }

        return $rolledBack;
    }

    /**
     * Refresh all migrations (reset + migrate)
     */
    public function refresh(): array
    {
        $this->reset();
        return $this->migrate();
    }

    /**
     * Get migration status
     */
    public function status(): array
    {
        $files = $this->getAllMigrationFiles();
        $ran = $this->getRanMigrations();
        $ranNames = array_column($ran, 'name');

        $status = [];
        foreach ($files as $file) {
            $name = $this->getMigrationName($file);
            $status[] = [
                'name' => $name,
                'ran' => in_array($name, $ranNames),
                'batch' => $this->getBatchNumber($name, $ran)
            ];
        }

        return $status;
    }

    /**
     * Run a single migration
     */
    protected function runMigration(array $migration, int $batch): void
    {
        $instance = $this->loadMigration($migration['file']);
        
        try {
            $instance->up();
            $this->recordMigration($migration['name'], $batch);
        } catch (\Exception $e) {
            throw new \RuntimeException(
                "Migration failed: {$migration['name']}\n{$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Rollback a single migration
     */
    protected function rollbackMigration(array $migration): void
    {
        $file = $this->findMigrationFile($migration['name']);
        
        if (!$file) {
            throw new \RuntimeException("Migration file not found: {$migration['name']}");
        }

        $instance = $this->loadMigration($file);
        
        try {
            $instance->down();
            $this->removeMigration($migration['name']);
        } catch (\Exception $e) {
            throw new \RuntimeException(
                "Rollback failed: {$migration['name']}\n{$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Load migration instance from file
     */
    protected function loadMigration(string $file): Migration
    {
        require_once $file;
        
        $name = $this->getMigrationName($file);
        $className = $this->getClassName($name);
        
        if (!class_exists($className)) {
            throw new \RuntimeException("Migration class not found: {$className}");
        }

        return new $className();
    }

    /**
     * Get all migration files
     */
    protected function getAllMigrationFiles(): array
    {
        if (!is_dir($this->migrationsPath)) {
            return [];
        }

        $files = [];
        $iterator = new DirectoryIterator($this->migrationsPath);

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        sort($files);
        return $files;
    }

    /**
     * Get pending migrations
     */
    protected function getPendingMigrations(): array
    {
        $files = $this->getAllMigrationFiles();
        $ran = array_column($this->getRanMigrations(), 'name');
        $pending = [];

        foreach ($files as $file) {
            $name = $this->getMigrationName($file);
            
            if (!in_array($name, $ran)) {
                $pending[] = [
                    'name' => $name,
                    'file' => $file
                ];
            }
        }

        return $pending;
    }

    /**
     * Get migrations that have been run
     */
    protected function getRanMigrations(): array
    {
        return Capsule::table($this->migrationsTable)
            ->orderBy('id', 'asc')
            ->get()
            ->toArray();
    }

    /**
     * Get last batch of migrations
     */
    protected function getLastBatch(): array
    {
        $lastBatch = Capsule::table($this->migrationsTable)
            ->max('batch');

        if (!$lastBatch) {
            return [];
        }

        return Capsule::table($this->migrationsTable)
            ->where('batch', $lastBatch)
            ->orderBy('id', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Record a migration
     */
    protected function recordMigration(string $name, int $batch): void
    {
        Capsule::table($this->migrationsTable)->insert([
            'name' => $name,
            'batch' => $batch,
            'migrated_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Remove a migration record
     */
    protected function removeMigration(string $name): void
    {
        Capsule::table($this->migrationsTable)
            ->where('name', $name)
            ->delete();
    }

    /**
     * Get next batch number
     */
    protected function getNextBatchNumber(): int
    {
        $max = Capsule::table($this->migrationsTable)->max('batch');
        return ($max ?? 0) + 1;
    }

    /**
     * Get batch number for a migration
     */
    protected function getBatchNumber(string $name, array $ran): ?int
    {
        foreach ($ran as $migration) {
            if ($migration['name'] === $name) {
                return $migration['batch'];
            }
        }
        return null;
    }

    /**
     * Find migration file by name
     */
    protected function findMigrationFile(string $name): ?string
    {
        $files = $this->getAllMigrationFiles();
        
        foreach ($files as $file) {
            if ($this->getMigrationName($file) === $name) {
                return $file;
            }
        }

        return null;
    }

    /**
     * Get migration name from file
     */
    protected function getMigrationName(string $file): string
    {
        return basename($file, '.php');
    }

    /**
     * Get class name from migration name
     */
    protected function getClassName(string $name): string
    {
        // Remove timestamp prefix (e.g., 20250101_120000_)
        $parts = explode('_', $name, 3);
        $className = $parts[2] ?? $name;
        
        // Convert to PascalCase
        return str_replace('_', '', ucwords($className, '_'));
    }

    /**
     * Ensure migrations table exists
     */
    protected function ensureMigrationsTableExists(): void
    {
        $schema = new Schema(Capsule::connection());

        if (!$schema->hasTable($this->migrationsTable)) {
            $schema->create($this->migrationsTable, function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->integer('batch');
                $table->datetime('migrated_at');
            });
        }
    }
}