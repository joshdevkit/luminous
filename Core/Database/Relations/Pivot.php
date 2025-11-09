<?php

namespace Core\Database\Relations;

use Core\Database\Model;

class Pivot extends Model
{
    /**
     * The name of the pivot table.
     */
    protected string $pivotTable;

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = false;

    /**
     * Create a new pivot model instance.
     */
    public function __construct(array $attributes = [], ?string $table = null)
    {
        if ($table) {
            $this->pivotTable = $table;
            $this->table = $table;
        }

        parent::__construct($attributes);
    }

    /**
     * Get the table associated with the model.
     */
    public function getTable(): string
    {
        return $this->pivotTable ?? parent::getTable();
    }

    /**
     * Set the table name for the pivot model.
     */
    public function setPivotTable(string $table): self
    {
        $this->pivotTable = $table;
        $this->table = $table;
        return $this;
    }
}