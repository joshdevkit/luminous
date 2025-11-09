<?php

namespace Core\Database\Schema;

class Column
{
    protected string $type;
    protected string $name;
    protected array $options;
    protected bool $isNullable = false;
    protected mixed $defaultValue = null;
    protected bool $hasDefault = false;
    protected bool $isUnsigned = false;
    protected bool $isAutoIncrement = false;
    protected bool $isPrimary = false;
    protected bool $isUnique = false;
    protected ?string $after = null;
    protected ?string $comment = null;
    protected ?string $checkConstraint = null;
    protected string $driver;

    public function __construct(string $type, string $name, array $options = [], string $driver = 'mysql')
    {
        $this->type = $type;
        $this->name = $name;
        $this->options = $options;
        $this->driver = $driver;
    }

    protected function quoteIdentifier(string $identifier): string
    {
        return match($this->driver) {
            'pgsql', 'sqlite' => "\"{$identifier}\"",
            'sqlsrv', 'mssql' => "[{$identifier}]",
            default => "`{$identifier}`"
        };
    }

    public function nullable(): self
    {
        $this->isNullable = true;
        return $this;
    }

    public function default(mixed $value): self
    {
        $this->defaultValue = $value;
        $this->hasDefault = true;
        return $this;
    }

    public function unsigned(): self
    {
        // PostgreSQL and SQLite don't support UNSIGNED
        if (!in_array($this->driver, ['pgsql', 'sqlite'])) {
            $this->isUnsigned = true;
        }
        return $this;
    }

    public function autoIncrement(): self
    {
        $this->isAutoIncrement = true;
        return $this;
    }

    public function primary(): self
    {
        $this->isPrimary = true;
        return $this;
    }

    public function unique(): self
    {
        $this->isUnique = true;
        return $this;
    }

    public function after(string $column): self
    {
        $this->after = $column;
        return $this;
    }

    public function comment(string $comment): self
    {
        $this->comment = $comment;
        return $this;
    }

    public function check(string $constraint): self
    {
        $this->checkConstraint = $constraint;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isPrimary(): bool
    {
        return $this->isPrimary;
    }

    public function isAutoIncrement(): bool
    {
        return $this->isAutoIncrement;
    }

    public function toSql(): string
    {
        $sql = $this->quoteIdentifier($this->name) . ' ';

        // Build type definition
        $sql .= $this->buildTypeDefinition();

        // Add UNSIGNED (MySQL only)
        if ($this->isUnsigned && $this->driver === 'mysql') {
            $sql .= ' UNSIGNED';
        }

        // Add AUTO_INCREMENT / AUTOINCREMENT
        if ($this->isAutoIncrement) {
            if ($this->driver === 'sqlite') {
                // SQLite requires INTEGER PRIMARY KEY AUTOINCREMENT
                // Must be added as PRIMARY KEY first
                $sql .= ' PRIMARY KEY AUTOINCREMENT';
                $this->isPrimary = true; // Mark as primary so Blueprint doesn't add it again
            } elseif ($this->driver === 'pgsql') {
                // Already handled by SERIAL type
            } elseif (in_array($this->driver, ['sqlsrv', 'mssql'])) {
                $sql .= ' IDENTITY(1,1)';
            } else {
                // MySQL
                $sql .= ' AUTO_INCREMENT';
            }
        }

        // Add NULL/NOT NULL (skip if already handled by SQLite AUTOINCREMENT)
        if (!($this->driver === 'sqlite' && $this->isAutoIncrement)) {
            if ($this->isNullable) {
                $sql .= ' NULL';
            } else {
                // Don't add NOT NULL for SERIAL in PostgreSQL (it's implicit)
                if (!($this->driver === 'pgsql' && $this->type === 'SERIAL')) {
                    $sql .= ' NOT NULL';
                }
            }
        }

        // Add DEFAULT (skip if autoincrement)
        if ($this->hasDefault && !$this->isAutoIncrement) {
            $sql .= ' DEFAULT ' . $this->formatDefaultValue($this->defaultValue);
        }

        // Add UNIQUE
        if ($this->isUnique) {
            $sql .= ' UNIQUE';
        }

        // Add PRIMARY KEY inline (MySQL only, and only if not auto-increment)
        // For MySQL with AUTO_INCREMENT, primary key is handled separately
        if ($this->isPrimary && $this->driver === 'mysql' && !$this->isAutoIncrement) {
            $sql .= ' PRIMARY KEY';
        }

        // Add CHECK constraint
        if ($this->checkConstraint) {
            $sql .= ' CHECK (' . $this->checkConstraint . ')';
        }

        // Add COMMENT (MySQL only)
        if ($this->comment && $this->driver === 'mysql') {
            $sql .= " COMMENT '{$this->comment}'";
        }

        // Add AFTER (MySQL only)
        if ($this->after && $this->driver === 'mysql') {
            $sql .= ' AFTER ' . $this->quoteIdentifier($this->after);
        }

        return $sql;
    }

    public function buildTypeDefinition(): string
    {
        $type = $this->type;

        // Handle length/precision
        if (isset($this->options['length'])) {
            if ($this->options['length'] === 'MAX') {
                return "{$type}(MAX)";
            }
            // SQLite doesn't require length for most types, but we include it for compatibility
            return "{$type}({$this->options['length']})";
        }

        if (isset($this->options['precision']) && isset($this->options['scale'])) {
            // SQLite doesn't use precision/scale for REAL, but we can include for other DBs
            if ($this->driver === 'sqlite' && $type === 'REAL') {
                return $type;
            }
            return "{$type}({$this->options['precision']},{$this->options['scale']})";
        }

        // Handle ENUM values
        if (isset($this->options['values'])) {
            $values = array_map(fn($v) => "'{$v}'", $this->options['values']);
            return "{$type}(" . implode(',', $values) . ")";
        }

        return $type;
    }

    protected function formatDefaultValue(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            if ($this->driver === 'pgsql') {
                return $value ? 'true' : 'false';
            }
            return $value ? '1' : '0';
        }

        if (is_numeric($value)) {
            return (string)$value;
        }

        if (is_string($value)) {
            // Handle SQL functions
            if (in_array(strtoupper($value), ['CURRENT_TIMESTAMP', 'NOW()', 'GETDATE()'])) {
                if ($this->driver === 'pgsql') {
                    return 'CURRENT_TIMESTAMP';
                } elseif (in_array($this->driver, ['sqlsrv', 'mssql'])) {
                    return 'GETDATE()';
                } elseif ($this->driver === 'sqlite') {
                    return 'CURRENT_TIMESTAMP';
                }
                return $value;
            }
            return "'{$value}'";
        }

        return "'{$value}'";
    }
}