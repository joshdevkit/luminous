<?php

namespace Core\Contracts\Database;

use Core\Support\Collection;

/**
 * Interface QueryBuilderInterface
 *
 * Defines a contract for a fluent, chainable query builder
 * capable of performing CRUD operations, aggregations, and
 * result retrieval on database tables.
 *
 * @package Core\Contracts\Database
 */
interface QueryBuilderInterface
{
    /**
     * Set the table name for the query.
     *
     * @param string $table The name of the database table.
     * @return static
     */
    public function table(string $table): self;

    /**
     * Specify the columns to select in the query.
     *
     * @param array|string $columns The columns to select. Defaults to all columns (*).
     * @return static
     */
    public function select(array|string $columns = ['*']): self;

    /**
     * Add a basic WHERE clause to the query.
     *
     * @param string $column   The column name.
     * @param mixed|null $operator The operator or value if using the default '='.
     * @param mixed|null $value    The value to compare with.
     * @return static
     */
    public function where(string $column, mixed $operator = null, mixed $value = null): self;

    /**
     * Add a WHERE IN clause to the query.
     *
     * @param string $column The column name.
     * @param array $values  The list of values to match.
     * @return static
     */
    public function whereIn(string $column, array $values): self;

    /**
     * Add an ORDER BY clause to the query.
     *
     * @param string $column    The column to order by.
     * @param string $direction The sort direction ('asc' or 'desc').
     * @return static
     */
    public function orderBy(string $column, string $direction = 'asc'): self;

    /**
     * Limit the number of records returned.
     *
     * @param int $limit The number of records to return.
     * @return static
     */
    public function limit(int $limit): self;

    /**
     * Skip a number of records before returning results.
     *
     * @param int $offset The number of records to skip.
     * @return static
     */
    public function offset(int $offset): self;

    /**
     * Execute the query and get the results as a collection.
     *
     * @return Collection The resulting records.
     */
    public function get(): Collection;

    /**
     * Get the maximum value of a given column.
     *
     * @param string $column The column name.
     * @return mixed The maximum value.
     */
    public function max(string $column): mixed;

    /**
     * Get the minimum value of a given column.
     *
     * @param string $column The column name.
     * @return mixed The minimum value.
     */
    public function min(string $column): mixed;

    /**
     * Get the sum of the values in a given column.
     *
     * @param string $column The column name.
     * @return mixed The sum of values.
     */
    public function sum(string $column): mixed;

    /**
     * Get the average of the values in a given column.
     *
     * @param string $column The column name.
     * @return mixed The average value.
     */
    public function avg(string $column): mixed;

    /**
     * Perform a generic aggregation function (e.g. COUNT, MAX, MIN).
     *
     * @param string $function The aggregation function name.
     * @param string $column   The column to aggregate.
     * @return mixed The result of the aggregation.
     */
    public function aggregate(string $function, string $column): mixed;

    /**
     * Get the first record from the query results.
     *
     * @return array|null The first record, or null if none found.
     */
    public function first(): ?array;

    /**
     * Find a record by its primary key.
     *
     * @param mixed $id The record’s primary key.
     * @return array|null The record, or null if not found.
     */
    public function find(mixed $id): ?array;

    /**
     * Insert a new record into the database.
     *
     * @param array $data The key-value pairs to insert.
     * @return bool True on success, false on failure.
     */
    public function insert(array $data): bool;

    /**
     * Update existing records that match the query.
     *
     * @param array $data The key-value pairs to update.
     * @return int The number of affected rows.
     */
    public function update(array $data): int;

    /**
     * Delete records that match the query.
     *
     * @return int The number of deleted rows.
     */
    public function delete(): int;

    /**
     * Count the number of records that match the query.
     *
     * @return int The number of matching records.
     */
    public function count(): int;
}
