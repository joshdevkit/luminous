<?php

namespace Core\Database\Schema;

class Blueprint
{
    protected string $table;
    protected string $mode;
    protected array $columns = [];
    protected array $indexes = [];
    protected array $foreignKeys = [];
    protected array $commands = [];
    protected ?string $engine = 'InnoDB';
    protected ?string $charset = 'utf8mb4';
    protected ?string $collation = 'utf8mb4_unicode_ci';
    protected string $driver;

    public function __construct(string $table, string $mode = 'create', string $driver = 'mysql')
    {
        $this->table = $table;
        $this->mode = $mode;
        $this->driver = $driver;
    }

    // ============================================
    // Quote Helpers
    // ============================================

    protected function quoteIdentifier(string $identifier): string
    {
        return match ($this->driver) {
            'pgsql', 'sqlite' => "\"{$identifier}\"",
            'sqlsrv', 'mssql' => "[{$identifier}]",
            default => "`{$identifier}`"
        };
    }

    // ============================================
    // Column Types
    // ============================================

    public function id(string $name = 'id'): Column
    {
        if ($this->driver === 'sqlite') {
            $column = $this->addColumn('INTEGER', $name)->autoIncrement();
            return $column;
        } elseif ($this->driver === 'pgsql') {
            $column = $this->addColumn('SERIAL', $name);
            $this->addPrimaryKey($name);
            return $column;
        } elseif (in_array($this->driver, ['sqlsrv', 'mssql'])) {
            $column = $this->addColumn('INT', $name)->autoIncrement();
            $this->addPrimaryKey($name);
            return $column;
        }
        
        $column = $this->bigInteger($name)->unsigned()->autoIncrement();
        $this->addPrimaryKey($name);
        return $column;
    }

    public function uuid(string $name = 'uuid'): Column
    {
        $column = $this->char($name, 36);
        $this->addPrimaryKey($name);
        return $column;
    }

    protected function addPrimaryKey(string $column): void
    {
        foreach ($this->indexes as $index) {
            if ($index['type'] === 'PRIMARY KEY' && in_array($column, $index['columns'])) {
                return;
            }
        }
        
        $this->indexes[] = [
            'type' => 'PRIMARY KEY',
            'name' => 'PRIMARY',
            'columns' => [$column]
        ];
    }

    public function string(string $name, int $length = 255): Column
    {
        return $this->addColumn('VARCHAR', $name, ['length' => $length]);
    }

    public function text(string $name): Column
    {
        return $this->addColumn('TEXT', $name);
    }

    public function mediumText(string $name): Column
    {
        $type = in_array($this->driver, ['pgsql', 'sqlite']) ? 'TEXT' : 'MEDIUMTEXT';
        return $this->addColumn($type, $name);
    }

    public function longText(string $name): Column
    {
        $type = in_array($this->driver, ['pgsql', 'sqlite']) ? 'TEXT' : 'LONGTEXT';
        return $this->addColumn($type, $name);
    }

    public function integer(string $name): Column
    {
        return $this->addColumn('INT', $name);
    }

    public function bigInteger(string $name): Column
    {
        $type = $this->driver === 'sqlite' ? 'INTEGER' : 'BIGINT';
        return $this->addColumn($type, $name);
    }

    public function tinyInteger(string $name): Column
    {
        $type = match($this->driver) {
            'pgsql' => 'SMALLINT',
            'sqlite' => 'INTEGER',
            default => 'TINYINT'
        };
        return $this->addColumn($type, $name);
    }

    public function decimal(string $name, int $precision = 8, int $scale = 2): Column
    {
        $type = $this->driver === 'sqlite' ? 'REAL' : 'DECIMAL';
        return $this->addColumn($type, $name, [
            'precision' => $precision,
            'scale' => $scale
        ]);
    }

    public function smallInteger(string $name): Column
    {
        $type = $this->driver === 'sqlite' ? 'INTEGER' : 'SMALLINT';
        return $this->addColumn($type, $name);
    }

    public function mediumInteger(string $name): Column
    {
        $type = match($this->driver) {
            'pgsql' => 'INTEGER',
            'sqlite' => 'INTEGER',
            default => 'MEDIUMINT'
        };
        return $this->addColumn($type, $name);
    }

    public function float(string $name, int $precision = 8, int $scale = 2): Column
    {
        $type = $this->driver === 'sqlite' ? 'REAL' : 'FLOAT';
        return $this->addColumn($type, $name, [
            'precision' => $precision,
            'scale' => $scale
        ]);
    }

    public function double(string $name, int $precision = 15, int $scale = 8): Column
    {
        $type = match($this->driver) {
            'pgsql' => 'DOUBLE PRECISION',
            'sqlite' => 'REAL',
            default => 'DOUBLE'
        };
        return $this->addColumn($type, $name, [
            'precision' => $precision,
            'scale' => $scale
        ]);
    }

    public function unsignedInteger(string $name): Column
    {
        $column = $this->addColumn('INT', $name);
        if (!in_array($this->driver, ['pgsql', 'sqlite'])) {
            $column->unsigned();
        }
        return $column;
    }

    public function unsignedBigInteger(string $name): Column
    {
        $type = $this->driver === 'sqlite' ? 'INTEGER' : 'BIGINT';
        $column = $this->addColumn($type, $name);
        if (!in_array($this->driver, ['pgsql', 'sqlite'])) {
            $column->unsigned();
        }
        return $column;
    }

    public function unsignedSmallInteger(string $name): Column
    {
        $type = $this->driver === 'sqlite' ? 'INTEGER' : 'SMALLINT';
        $column = $this->addColumn($type, $name);
        if (!in_array($this->driver, ['pgsql', 'sqlite'])) {
            $column->unsigned();
        }
        return $column;
    }

    public function unsignedMediumInteger(string $name): Column
    {
        $type = match($this->driver) {
            'pgsql' => 'INTEGER',
            'sqlite' => 'INTEGER',
            default => 'MEDIUMINT'
        };
        $column = $this->addColumn($type, $name);
        if (!in_array($this->driver, ['pgsql', 'sqlite'])) {
            $column->unsigned();
        }
        return $column;
    }

    public function boolean(string $name): Column
    {
        if ($this->driver === 'pgsql') {
            return $this->addColumn('BOOLEAN', $name)->default('false');
        } elseif (in_array($this->driver, ['sqlsrv', 'mssql'])) {
            return $this->addColumn('BIT', $name)->default(0);
        }
        $type = $this->driver === 'sqlite' ? 'INTEGER' : 'TINYINT';
        return $this->addColumn($type, $name)->default(0);
    }

    public function date(string $name): Column
    {
        $type = $this->driver === 'sqlite' ? 'TEXT' : 'DATE';
        return $this->addColumn($type, $name);
    }

    public function datetime(string $name): Column
    {
        $type = match ($this->driver) {
            'pgsql' => 'TIMESTAMP',
            'sqlite' => 'TEXT',
            'sqlsrv', 'mssql' => 'DATETIME2',
            default => 'DATETIME',
        };
        return $this->addColumn($type, $name);
    }

    public function timestamp(string $name): Column
    {
        $type = match($this->driver) {
            'sqlite' => 'TEXT',
            'sqlsrv', 'mssql' => 'DATETIME2',
            default => 'TIMESTAMP'
        };
        return $this->addColumn($type, $name);
    }

    public function timestamps(): void
    {
        $this->timestamp('created_at')->nullable();
        $this->timestamp('updated_at')->nullable();
    }

    public function json(string $name): Column
    {
        if ($this->driver === 'pgsql') {
            return $this->addColumn('JSONB', $name);
        } elseif (in_array($this->driver, ['sqlsrv', 'mssql'])) {
            return $this->addColumn('NVARCHAR', $name, ['length' => 'MAX']);
        } elseif ($this->driver === 'sqlite') {
            return $this->addColumn('TEXT', $name);
        }
        return $this->addColumn('JSON', $name);
    }

    public function enum(string $name, array $values): Column
    {
        if (in_array($this->driver, ['pgsql', 'sqlite'])) {
            $column = $this->addColumn('VARCHAR', $name, ['length' => 255]);
            $valuesList = implode("', '", $values);
            $column->check("{$this->quoteIdentifier($name)} IN ('{$valuesList}')");
            return $column;
        }
        return $this->addColumn('ENUM', $name, ['values' => $values]);
    }

    public function char(string $name, int $length = 255): Column
    {
        return $this->addColumn('CHAR', $name, ['length' => $length]);
    }

    // ============================================
    // Polymorphic Column Helpers
    // ============================================

    public function morphs(string $name, ?string $indexName = null): self
    {
        $this->unsignedBigInteger("{$name}_id");
        $this->string("{$name}_type");
        
        return $this;
    }

    public function nullableMorphs(string $name, ?string $indexName = null): self
    {
        $this->unsignedBigInteger("{$name}_id")->nullable();
        $this->string("{$name}_type")->nullable();
        
        return $this;
    }

    public function uuidMorphs(string $name, ?string $indexName = null): self
    {
        $this->char("{$name}_id", 36);
        $this->string("{$name}_type");
        
        return $this;
    }

    public function nullableUuidMorphs(string $name, ?string $indexName = null): self
    {
        $this->char("{$name}_id", 36)->nullable();
        $this->string("{$name}_type")->nullable();
        
        return $this;
    }

    // ============================================
    // Indexes
    // ============================================

    public function index(string|array $columns, ?string $name = null): self
    {
        $columns = is_array($columns) ? $columns : [$columns];
        $name = $name ?? $this->createIndexName('index', $columns);
        $this->indexes[] = ['type' => 'INDEX', 'name' => $name, 'columns' => $columns];
        return $this;
    }

    public function unique(string|array $columns, ?string $name = null): self
    {
        $columns = is_array($columns) ? $columns : [$columns];
        $name = $name ?? $this->createIndexName('unique', $columns);
        $this->indexes[] = ['type' => 'UNIQUE', 'name' => $name, 'columns' => $columns];
        return $this;
    }

    public function foreign(string $column): ForeignKey
    {
        $indexName = $this->createIndexName('index', [$column]);
        $indexExists = false;

        foreach ($this->indexes as $index) {
            if ($index['name'] === $indexName) {
                $indexExists = true;
                break;
            }
        }

        if (!$indexExists) {
            $this->index($column);
        }

        $foreignKey = new ForeignKey($this, $column, $this->driver);
        $this->foreignKeys[] = $foreignKey;
        return $foreignKey;
    }

    // ============================================
    // Alter Table Commands
    // ============================================

    public function addColumn(string $type, string $name, array $options = []): Column
    {
        $column = new Column($type, $name, $options, $this->driver);
        $this->columns[] = $column;
        return $column;
    }

    public function dropColumn(string|array $columns): self
    {
        $columns = is_array($columns) ? $columns : [$columns];
        $quotedTable = $this->quoteIdentifier($this->table);

        foreach ($columns as $column) {
            $quotedColumn = $this->quoteIdentifier($column);
            
            if ($this->driver === 'sqlite') {
                // SQLite doesn't support DROP COLUMN directly (requires table recreation)
                $this->commands[] = [
                    'type' => 'drop_column',
                    'column' => $column,
                    'note' => 'SQLite requires manual table recreation for DROP COLUMN'
                ];
            } else {
                $this->commands[] = "ALTER TABLE {$quotedTable} DROP COLUMN {$quotedColumn}";
            }
        }
        
        return $this;
    }

    public function renameColumn(string $from, string $to): self
    {
        $quotedTable = $this->quoteIdentifier($this->table);
        $quotedFrom = $this->quoteIdentifier($from);
        $quotedTo = $this->quoteIdentifier($to);

        if ($this->driver === 'sqlite') {
            // SQLite requires ALTER TABLE RENAME COLUMN (3.25.0+)
            $this->commands[] = "ALTER TABLE {$quotedTable} RENAME COLUMN {$quotedFrom} TO {$quotedTo}";
        } elseif (in_array($this->driver, ['sqlsrv', 'mssql'])) {
            // SQL Server uses sp_rename
            $this->commands[] = "EXEC sp_rename '{$this->table}.{$from}', '{$to}', 'COLUMN'";
        } else {
            // MySQL and PostgreSQL
            $this->commands[] = "ALTER TABLE {$quotedTable} RENAME COLUMN {$quotedFrom} TO {$quotedTo}";
        }
        
        return $this;
    }

    public function modifyColumn(string $name, string $type, array $options = []): self
    {
        $quotedTable = $this->quoteIdentifier($this->table);
        $column = new Column($type, $name, $options, $this->driver);
        
        if ($this->driver === 'mysql') {
            $this->commands[] = "ALTER TABLE {$quotedTable} MODIFY COLUMN " . $column->toSql();
        } elseif ($this->driver === 'pgsql') {
            // PostgreSQL requires separate ALTER statements for different modifications
            $quotedName = $this->quoteIdentifier($name);
            $this->commands[] = "ALTER TABLE {$quotedTable} ALTER COLUMN {$quotedName} TYPE " . $column->buildTypeDefinition();
        } elseif ($this->driver === 'sqlite') {
            $this->commands[] = [
                'type' => 'modify_column',
                'column' => $name,
                'note' => 'SQLite requires manual table recreation for MODIFY COLUMN'
            ];
        } else {
            // SQL Server
            $this->commands[] = "ALTER TABLE {$quotedTable} ALTER COLUMN " . $column->toSql();
        }
        
        return $this;
    }

    public function dropIndex(string $indexName): self
    {
        $quotedTable = $this->quoteIdentifier($this->table);
        $quotedIndex = $this->quoteIdentifier($indexName);

        if ($this->driver === 'mysql') {
            $this->commands[] = "ALTER TABLE {$quotedTable} DROP INDEX {$quotedIndex}";
        } elseif ($this->driver === 'pgsql' || $this->driver === 'sqlite') {
            $this->commands[] = "DROP INDEX {$quotedIndex}";
        } else {
            // SQL Server
            $this->commands[] = "DROP INDEX {$quotedIndex} ON {$quotedTable}";
        }
        
        return $this;
    }

    public function dropPrimary(): self
    {
        $quotedTable = $this->quoteIdentifier($this->table);

        if ($this->driver === 'mysql') {
            $this->commands[] = "ALTER TABLE {$quotedTable} DROP PRIMARY KEY";
        } elseif ($this->driver === 'pgsql') {
            $this->commands[] = "ALTER TABLE {$quotedTable} DROP CONSTRAINT {$this->table}_pkey";
        } elseif ($this->driver === 'sqlite') {
            $this->commands[] = [
                'type' => 'drop_primary',
                'note' => 'SQLite requires manual table recreation for DROP PRIMARY KEY'
            ];
        } else {
            // SQL Server
            $this->commands[] = "ALTER TABLE {$quotedTable} DROP CONSTRAINT PK_{$this->table}";
        }
        
        return $this;
    }

    public function dropUnique(string $indexName): self
    {
        return $this->dropIndex($indexName);
    }

    public function dropForeign(string|array $index): self
    {
        $indexes = is_array($index) ? $index : [$index];
        $quotedTable = $this->quoteIdentifier($this->table);

        foreach ($indexes as $idx) {
            $quotedIndex = $this->quoteIdentifier($idx);
            
            if ($this->driver === 'mysql') {
                $this->commands[] = "ALTER TABLE {$quotedTable} DROP FOREIGN KEY {$quotedIndex}";
            } elseif ($this->driver === 'pgsql') {
                $this->commands[] = "ALTER TABLE {$quotedTable} DROP CONSTRAINT {$quotedIndex}";
            } elseif ($this->driver === 'sqlite') {
                $this->commands[] = [
                    'type' => 'drop_foreign',
                    'constraint' => $idx,
                    'note' => 'SQLite requires manual table recreation for DROP FOREIGN KEY'
                ];
            } else {
                // SQL Server
                $this->commands[] = "ALTER TABLE {$quotedTable} DROP CONSTRAINT {$quotedIndex}";
            }
        }
        
        return $this;
    }

    // ============================================
    // Table Options
    // ============================================

    public function engine(string $engine): self
    {
        $this->engine = $engine;
        return $this;
    }

    public function charset(string $charset): self
    {
        $this->charset = $charset;
        return $this;
    }

    public function collation(string $collation): self
    {
        $this->collation = $collation;
        return $this;
    }

    // ============================================
    // SQL Generation
    // ============================================

    public function toSql(): string
    {
        if ($this->mode === 'create') {
            return $this->buildCreateTableSql();
        }

        return '';
    }

    protected function buildCreateTableSql(): string
    {
        $quotedTable = $this->quoteIdentifier($this->table);
        $sql = "CREATE TABLE {$quotedTable} (\n";

        $columnDefinitions = [];
        foreach ($this->columns as $column) {
            $columnDefinitions[] = '  ' . $column->toSql();
        }

        $sql .= implode(",\n", $columnDefinitions);

        foreach ($this->indexes as $index) {
            $quotedColumns = array_map(fn($col) => $this->quoteIdentifier($col), $index['columns']);
            $columns = implode(', ', $quotedColumns);

            if ($index['type'] === 'PRIMARY KEY') {
                if ($this->shouldSkipInlinePrimaryKey($index['columns'])) {
                    continue;
                }
                $sql .= ",\n  PRIMARY KEY ({$columns})";
            } else {
                $quotedName = $this->quoteIdentifier($index['name']);
                $indexType = $index['type'] === 'UNIQUE' ? 'UNIQUE' : '';
                if (in_array($this->driver, ['pgsql', 'sqlite'])) {
                    continue;
                } else {
                    $sql .= ",\n  {$indexType} INDEX {$quotedName} ({$columns})";
                }
            }
        }

        foreach ($this->foreignKeys as $foreignKey) {
            $sql .= ",\n  " . $foreignKey->toSql();
        }

        $sql .= "\n)";

        if ($this->driver === 'mysql') {
            if ($this->engine) {
                $sql .= " ENGINE={$this->engine}";
            }
            if ($this->charset) {
                $sql .= " DEFAULT CHARSET={$this->charset}";
            }
            if ($this->collation) {
                $sql .= " COLLATE={$this->collation}";
            }
        }

        return $sql;
    }

    protected function shouldSkipInlinePrimaryKey(array $pkColumns): bool
    {
        if ($this->driver !== 'sqlite' || count($pkColumns) !== 1) {
            return false;
        }

        $pkColumn = $pkColumns[0];
        foreach ($this->columns as $column) {
            if ($column->getName() === $pkColumn && $column->isAutoIncrement()) {
                return true;
            }
        }

        return false;
    }

    public function getCommands(): array
    {
        // If in alter mode, return the commands as-is
        if ($this->mode === 'alter') {
            $alterCommands = $this->commands;
            $quotedTable = $this->quoteIdentifier($this->table);

            // Add column additions
            foreach ($this->columns as $column) {
                $alterCommands[] = "ALTER TABLE {$quotedTable} ADD COLUMN " . $column->toSql();
            }

            // Add index additions
            foreach ($this->indexes as $index) {
                $quotedColumns = array_map(fn($col) => $this->quoteIdentifier($col), $index['columns']);
                $columns = implode(', ', $quotedColumns);
                $quotedName = $this->quoteIdentifier($index['name']);

                if (in_array($this->driver, ['pgsql', 'sqlite'])) {
                    if ($index['type'] === 'PRIMARY KEY') {
                        $alterCommands[] = "ALTER TABLE {$quotedTable} ADD PRIMARY KEY ({$columns})";
                    } else {
                        $unique = $index['type'] === 'UNIQUE' ? 'UNIQUE ' : '';
                        $alterCommands[] = "CREATE {$unique}INDEX {$quotedName} ON {$quotedTable} ({$columns})";
                    }
                } else {
                    $alterCommands[] = "ALTER TABLE {$quotedTable} ADD {$index['type']} {$quotedName} ({$columns})";
                }
            }

            // Add foreign key additions
            foreach ($this->foreignKeys as $foreignKey) {
                $alterCommands[] = "ALTER TABLE {$quotedTable} ADD " . $foreignKey->toSql();
            }

            return $alterCommands;
        }

        // For create mode (used by PostgreSQL for separate index creation)
        $commands = $this->commands;
        $quotedTable = $this->quoteIdentifier($this->table);

        foreach ($this->indexes as $index) {
            $quotedColumns = array_map(fn($col) => $this->quoteIdentifier($col), $index['columns']);
            $columns = implode(', ', $quotedColumns);
            $quotedName = $this->quoteIdentifier($index['name']);

            if (in_array($this->driver, ['pgsql', 'sqlite'])) {
                if ($index['type'] === 'PRIMARY KEY') {
                    continue; // Already in CREATE TABLE
                } else {
                    $unique = $index['type'] === 'UNIQUE' ? 'UNIQUE ' : '';
                    $commands[] = "CREATE {$unique}INDEX {$quotedName} ON {$quotedTable} ({$columns})";
                }
            }
        }

        return $commands;
    }

    protected function createIndexName(string $type, array $columns): string
    {
        return $this->table . '_' . implode('_', $columns) . '_' . $type;
    }

    public function getTableName(): string
    {
        return $this->table;
    }

    public function getForeignKeys(): array
    {
        return $this->foreignKeys;
    }

    public function getDriver(): string
    {
        return $this->driver;
    }
}