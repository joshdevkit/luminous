<?php

namespace Core\Database\Schema;

class ForeignKey
{
    protected Blueprint $blueprint;
    protected string $column;
    protected ?string $referenceTable = null;
    protected string $referenceColumn = 'id';
    protected ?string $onDelete = null;
    protected ?string $onUpdate = null;
    protected ?string $constraintName = null;
    protected string $driver;

    public function __construct(Blueprint $blueprint, string $column, string $driver = 'mysql')
    {
        $this->blueprint = $blueprint;
        $this->column = $column;
        $this->driver = $driver;
    }

    protected function quoteIdentifier(string $identifier): string
    {
        return match($this->driver) {
            'pgsql' => "\"{$identifier}\"",
            'sqlite' => "\"{$identifier}\"",
            'sqlsrv', 'mssql' => "[{$identifier}]",
            default => "`{$identifier}`"
        };
    }

    public function references(string $column): self
    {
        $this->referenceColumn = $column;
        return $this;
    }

    public function on(string $table): self
    {
        $this->referenceTable = $table;
        return $this;
    }

    public function onDelete(string $action): self
    {
        $this->onDelete = strtoupper($action);
        return $this;
    }

    public function onUpdate(string $action): self
    {
        $this->onUpdate = strtoupper($action);
        return $this;
    }

    public function cascadeOnDelete(): self
    {
        return $this->onDelete('CASCADE');
    }

    public function nullOnDelete(): self
    {
        return $this->onDelete('SET NULL');
    }

    public function restrictOnDelete(): self
    {
        return $this->onDelete('RESTRICT');
    }

    public function cascadeOnUpdate(): self
    {
        return $this->onUpdate('CASCADE');
    }

    public function restrictOnUpdate(): self
    {
        return $this->onUpdate('RESTRICT');
    }

    public function name(string $name): self
    {
        $this->constraintName = $name;
        return $this;
    }

    public function toSql(): string
    {
        if (!$this->referenceTable) {
            throw new \RuntimeException("Foreign key must reference a table");
        }

        $constraintName = $this->constraintName ?? 
            $this->blueprint->getTableName() . '_' . $this->column . '_foreign';

        // SQLite has a simpler foreign key syntax (no named constraints in inline definition)
        if ($this->driver === 'sqlite') {
            $sql = 'FOREIGN KEY (' . $this->quoteIdentifier($this->column) . ')';
            $sql .= ' REFERENCES ' . $this->quoteIdentifier($this->referenceTable);
            $sql .= ' (' . $this->quoteIdentifier($this->referenceColumn) . ')';
        } else {
            $sql = 'CONSTRAINT ' . $this->quoteIdentifier($constraintName);
            $sql .= ' FOREIGN KEY (' . $this->quoteIdentifier($this->column) . ')';
            $sql .= ' REFERENCES ' . $this->quoteIdentifier($this->referenceTable);
            $sql .= ' (' . $this->quoteIdentifier($this->referenceColumn) . ')';
        }

        if ($this->onDelete) {
            $sql .= ' ON DELETE ' . $this->onDelete;
        }

        if ($this->onUpdate) {
            $sql .= ' ON UPDATE ' . $this->onUpdate;
        }

        return $sql;
    }
}