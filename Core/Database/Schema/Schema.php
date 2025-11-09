<?php

namespace Core\Database\Schema;

class Schema
{
    protected $connection;

    public function __construct($connection)
    {
        $this->connection = $connection;
    }

    /**
     * Get the database driver name
     */
    protected function getDriver(): string
    {
        return $this->connection->getConfig()['driver'] ?? 'mysql';
    }

    /**
     * Create a new table
     */
    public function create(string $table, callable $callback): void
    {
        $blueprint = new Blueprint($table, 'create', $this->getDriver());
        $callback($blueprint);

        $sql = $blueprint->toSql();
        $this->connection->unprepared($sql);
        
        // For PostgreSQL, create indexes separately
        if ($this->getDriver() === 'pgsql') {
            foreach ($blueprint->getCommands() as $command) {
                if (str_contains($command, 'CREATE INDEX') || str_contains($command, 'CREATE UNIQUE INDEX')) {
                    $this->connection->unprepared($command);
                }
            }
        }
    }

    /**
     * Modify an existing table
     */
    public function alter(string $table, callable $callback): void
    {
        $blueprint = new Blueprint($table, 'alter', $this->getDriver());
        $callback($blueprint);

        foreach ($blueprint->getCommands() as $sql) {
            $this->connection->unprepared($sql);
        }
    }

    /**
     * Drop a table (with foreign key handling)
     */
    public function drop(string $table): void
    {
        $driver = $this->getDriver();

        // Disable foreign key checks before dropping
        $this->disableForeignKeyChecks();

        try {
            switch ($driver) {
                case 'pgsql':
                    $this->connection->unprepared("DROP TABLE IF EXISTS \"{$table}\" CASCADE");
                    break;
                case 'sqlsrv':
                case 'mssql':
                    $this->connection->unprepared("IF OBJECT_ID('{$table}', 'U') IS NOT NULL DROP TABLE [{$table}]");
                    break;
                case 'sqlite':
                    // SQLite needs foreign keys disabled first
                    $this->connection->unprepared("DROP TABLE IF EXISTS `{$table}`");
                    break;
                case 'mysql':
                default:
                    $this->connection->unprepared("DROP TABLE IF EXISTS `{$table}`");
                    break;
            }
        } finally {
            // Re-enable foreign key checks
            $this->enableForeignKeyChecks();
        }
    }

    /**
     * Drop a table if it exists (with foreign key handling)
     */
    public function dropIfExists(string $table): void
    {
        if ($this->hasTable($table)) {
            $this->drop($table);
        }
    }

    /**
     * Disable foreign key checks
     */
    public function disableForeignKeyChecks(): void
    {
        $driver = $this->getDriver();

        try {
            switch ($driver) {
                case 'mysql':
                    $this->connection->unprepared('SET FOREIGN_KEY_CHECKS=0');
                    break;
                case 'pgsql':
                    // PostgreSQL doesn't have a global FK check disable
                    // Use CASCADE on DROP instead
                    break;
                case 'sqlite':
                    $this->connection->unprepared('PRAGMA foreign_keys = OFF');
                    break;
                case 'sqlsrv':
                case 'mssql':
                    // SQL Server doesn't have a simple global disable
                    // Individual constraints must be disabled
                    break;
            }
        } catch (\Exception $e) {
            // Ignore if already disabled or not supported
        }
    }

    /**
     * Enable foreign key checks
     */
    public function enableForeignKeyChecks(): void
    {
        $driver = $this->getDriver();

        try {
            switch ($driver) {
                case 'mysql':
                    $this->connection->unprepared('SET FOREIGN_KEY_CHECKS=1');
                    break;
                case 'pgsql':
                    // Not needed for PostgreSQL
                    break;
                case 'sqlite':
                    $this->connection->unprepared('PRAGMA foreign_keys = ON');
                    break;
                case 'sqlsrv':
                case 'mssql':
                    // Not needed for SQL Server
                    break;
            }
        } catch (\Exception $e) {
            // Ignore if already enabled
        }
    }

    /**
     * Check if table exists
     */
    public function hasTable(string $table): bool
    {
        $driver = $this->getDriver();
        switch ($driver) {
            case 'pgsql':
                $schema = $this->connection->getConfig()['schema'] ?? 'public';
                // PostgreSQL stores table names in lowercase by default
                $result = $this->connection->select(
                    "SELECT tablename FROM pg_tables 
                     WHERE schemaname = ? 
                       AND tablename = ?",
                    [$schema, strtolower($table)]
                );
                break;

            case 'sqlsrv':
            case 'mssql':
                $result = $this->connection->select(
                    "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES 
                     WHERE TABLE_TYPE = 'BASE TABLE' 
                       AND TABLE_NAME = ?",
                    [$table]
                );
                break;

            case 'sqlite':
                $result = $this->connection->select(
                    "SELECT name FROM sqlite_master 
                     WHERE type='table' AND name = ?",
                    [$table]
                );
                break;

            case 'mysql':
            default:
                $result = $this->connection->select(
                    "SELECT TABLE_NAME FROM information_schema.TABLES 
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
                    [$table]
                );
                break;
        }

        return !empty($result);
    }

    /**
     * Rename a table
     */
    public function rename(string $from, string $to): void
    {
        $driver = $this->getDriver();

        switch ($driver) {
            case 'pgsql':
                $this->connection->unprepared("ALTER TABLE \"{$from}\" RENAME TO \"{$to}\"");
                break;
            case 'sqlsrv':
            case 'mssql':
                $this->connection->unprepared("EXEC sp_rename '{$from}', '{$to}'");
                break;
            case 'sqlite':
                $this->connection->unprepared("ALTER TABLE `{$from}` RENAME TO `{$to}`");
                break;
            case 'mysql':
            default:
                $this->connection->unprepared("RENAME TABLE `{$from}` TO `{$to}`");
                break;
        }
    }
}