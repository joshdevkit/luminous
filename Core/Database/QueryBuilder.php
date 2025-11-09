<?php

namespace Core\Database;

use Core\Contracts\Database\ConnectionInterface;
use Core\Contracts\Database\QueryBuilderInterface;
use Core\Support\Collection;
use Core\Support\Str;

class QueryBuilder implements QueryBuilderInterface
{
    protected ConnectionInterface $connection;
    protected ?string $table = null;
    protected array $columns = ['*'];
    protected array $wheres = [];
    protected array $joins = [];
    protected array $bindings = [];
    protected ?string $orderByColumn = null;
    protected string $orderByDirection = 'asc';
    protected ?int $limitValue = null;
    protected ?int $offsetValue = null;
    protected string $primaryKey = 'id';
    protected ?Model $model = null;

    public function __construct(ConnectionInterface $connection, ?Model $model = null)
    {
        $this->connection = $connection;
        $this->model = $model;

        if ($this->model) {
            $this->primaryKey = $this->model->getKeyName();
        }
    }

    public function table(string $table): self
    {
        $this->table = $table;
        return $this;
    }

    public function setPrimaryKey(string $primaryKey): self
    {
        $this->primaryKey = $primaryKey;
        return $this;
    }

    public function getPrimaryKey(): string
    {
        return $this->primaryKey;
    }


    /**
     * Add a select clause to the existing query
     */
    public function addSelect(array|string $columns): self
    {
        $columns = is_array($columns) ? $columns : func_get_args();

        // Merge with existing columns, removing ['*'] if present
        if ($this->columns === ['*']) {
            $this->columns = $columns;
        } else {
            $this->columns = array_merge($this->columns, $columns);
        }

        return $this;
    }

    /**
     * Get the columns being selected
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    public function select(array|string $columns = ['*']): self
    {
        $this->columns = is_array($columns) ? $columns : func_get_args();
        return $this;
    }


    // ============================================
    // JOIN METHODS
    // ============================================

    /**
     * Add a join clause to the query
     */
    public function join(
        string $table,
        ?string $first = null,
        ?string $operator = null,
        ?string $second = null,
        string $type = 'INNER'
    ): self {
        // Auto-detect foreign key relationship if not specified
        if ($first === null && $second === null) {
            // Assume: users.id = user_infos.user_id
            $first = "{$this->table}.{$this->primaryKey}";
            $second = "{$table}." . $this->getSingular($this->table) . "_{$this->primaryKey}";
            $operator = '=';
        } elseif ($operator === null) {
            // If only two params provided, assume equality
            $operator = '=';
        }

        $this->joins[] = [
            'type' => strtoupper($type),
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second
        ];

        return $this;
    }

    /**
     * Add a left join clause to the query
     */
    public function leftJoin(
        string $table,
        ?string $first = null,
        ?string $operator = null,
        ?string $second = null
    ): self {
        return $this->join($table, $first, $operator, $second, 'LEFT');
    }

    /**
     * Add a right join clause to the query
     */
    public function rightJoin(
        string $table,
        ?string $first = null,
        ?string $operator = null,
        ?string $second = null
    ): self {
        return $this->join($table, $first, $operator, $second, 'RIGHT');
    }

    /**
     * Add a cross join clause to the query
     */
    public function crossJoin(string $table): self
    {
        $this->joins[] = [
            'type' => 'CROSS',
            'table' => $table,
            'first' => null,
            'operator' => null,
            'second' => null
        ];

        return $this;
    }
    
    /**
     * Get singular form of table name
     */
    protected function getSingular(string $table): string
    {
        // Handle common plural patterns
        if (str_ends_with($table, 'ies')) {
            return substr($table, 0, -3) . 'y';
        }

        if (str_ends_with($table, 'ses') || str_ends_with($table, 'ches') || str_ends_with($table, 'shes')) {
            return substr($table, 0, -2);
        }

        if (str_ends_with($table, 's')) {
            return substr($table, 0, -1);
        }

        return $table;
    }

    // ============================================
    // WHERE METHODS
    // ============================================

    public function where(string $column, mixed $operator = null, mixed $value = null): self
    {
        if ($value === null && $operator !== null) {
            $value = $operator;
            $operator = '=';
        }

        $this->wheres[] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => empty($this->wheres) ? '' : 'AND'
        ];

        $this->bindings[] = $value;

        return $this;
    }

    public function whereIn(string $column, array $values): self
    {
        $this->wheres[] = [
            'type' => 'in',
            'column' => $column,
            'values' => $values,
            'boolean' => empty($this->wheres) ? '' : 'AND'
        ];

        $this->bindings = array_merge($this->bindings, $values);

        return $this;
    }

    public function orWhere(string $column, mixed $operator = null, mixed $value = null): self
    {
        if ($value === null && $operator !== null) {
            $value = $operator;
            $operator = '=';
        }

        $this->wheres[] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'OR'
        ];

        $this->bindings[] = $value;

        return $this;
    }

    public function orderBy(string $column, string $direction = 'asc'): self
    {
        $this->orderByColumn = $column;
        $this->orderByDirection = strtolower($direction) === 'desc' ? 'desc' : 'asc';
        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limitValue = $limit;
        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offsetValue = $offset;
        return $this;
    }

    public function get(): Collection
    {
        $sql = $this->toSelectSql();
        $results = $this->connection->select($sql, $this->bindings);

        return new Collection($results);
    }

    // public function first(): ?array
    // {
    //     $this->limit(1);
    //     $results = $this->get();
    //     return $results->first();
    // }

    // public function firstOrFail(): array
    // {
    //     $result = $this->first();

    //     if ($result === null) {
    //         $table = $this->model?->getTable() ?? $this->table;
    //         $primaryKey = $this->model?->getKeyName();

    //         $value = null;
    //         if (!empty($this->wheres)) {
    //             $lastWhere = end($this->wheres);
    //             $value = $lastWhere['value'] ?? json_encode($lastWhere['values'] ?? []);
    //         }

    //         return [
    //             'success' => false,
    //             'message' => 'No record found.',
    //             // 'table' => $table,
    //             // 'primary_key' => $primaryKey,
    //             // 'searched_value' => $value,
    //         ];
    //         // return ;
    //         throw new \RuntimeException(
    //             "No query results for query table [{$table}] with [{$primaryKey}] value [{$value}]"
    //         );
    //     }

    //     return $result;
    // }

    public function first(): ?array
    {
        $this->limit(1);
        $results = $this->get();
        return $results->first() ?: null;
    }

    public function firstOrFail()
    {
        $result = $this->first();

        if ($result === null) {
            // $table = $this->model?->getTable() ?? $this->table;
            // $primaryKey = $this->model?->getKeyName();

            // $lastWhere = end($this->wheres) ?: [];
            // $value = $lastWhere['value'] ?? json_encode($lastWhere['values'] ?? []);
            // throw new \RuntimeException(
            //     "No query results for table [{$table}] with [{$primaryKey}] = [{$value}]"
            // );
            return null;
        }

        return $result;
    }



    public function find(mixed $id): ?array
    {
        $this->wheres = [];
        $this->bindings = [];

        return $this->where($this->primaryKey, $id)->first();
    }

    public function findOrFail(mixed $id): array
    {
        $result = $this->find($id);

        if ($result === null) {
            throw new \RuntimeException(
                "No query results for table [{$this->table}] with {$this->primaryKey} [{$id}]"
            );
        }

        return $result;
    }

    public function findOne(mixed $id)
    {
        return $this->find($id);
    }

    public function insert(array $data): bool
    {
        if (empty($data)) {
            return false;
        }

        $isMulti = isset($data[0]) && is_array($data[0]);

        if (!$isMulti) {
            $columns = array_keys($data);
            $values = array_values($data);
            $placeholders = array_fill(0, count($values), '?');

            $sql = sprintf(
                "INSERT INTO %s (%s) VALUES (%s)",
                $this->table,
                implode(', ', $columns),
                implode(', ', $placeholders)
            );

            return $this->connection->insert($sql, $values);
        }

        $columns = array_keys($data[0]); 
        $rowPlaceholder = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        $rowsSQL = [];
        $bindings = [];

        foreach ($data as $row) {
            $row = array_replace(array_fill_keys($columns, null), $row);
            $rowsSQL[] = $rowPlaceholder;
            $bindings = array_merge($bindings, array_values($row));
        }

        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES %s",
            $this->table,
            implode(', ', $columns),
            implode(', ', $rowsSQL)
        );

        return $this->connection->insert($sql, $bindings);
    }


    public function insertGetId(array $data): int
    {
        if ($this->insert($data)) {
            return $this->connection->lastInsertId();
        }

        return 0;
    }

    public function update(array $data): int
    {
        if (empty($data)) {
            return 0;
        }

        $sets = [];
        $bindings = [];

        foreach ($data as $column => $value) {
            $sets[] = "{$column} = ?";
            $bindings[] = $value;
        }

        $bindings = array_merge($bindings, $this->bindings);

        $sql = sprintf(
            "UPDATE %s SET %s%s",
            $this->table,
            implode(', ', $sets),
            $this->buildWhereClauses()
        );

        return $this->connection->update($sql, $bindings);
    }

    public function delete(): int
    {
        $sql = sprintf(
            "DELETE FROM %s%s",
            $this->table,
            $this->buildWhereClauses()
        );

        return $this->connection->delete($sql, $this->bindings);
    }

    public function count(): int
    {
        $originalColumns = $this->columns;
        $this->columns = ['COUNT(*) as count'];

        $result = $this->first();

        $this->columns = $originalColumns;

        return (int) ($result['count'] ?? 0);
    }

    public function exists(): bool
    {
        return $this->count() > 0;
    }


    /**
     * Get a single value from a column
     */
    public function value(string $column): mixed
    {
        $sql = "SELECT {$column} FROM {$this->table} LIMIT 1";
        $result = $this->connection->select($sql);
        return $result[0][$column] ?? null;
    }

    /**
     * Paginate the query results
     */
    public function paginate(?int $perPage = null, array $columns = ['*'], string $pageName = 'page', ?int $page = null): \Core\Support\Paginator
    {
        // Get per page from model if available, otherwise use default
        $perPage = $perPage ?? ($this->model?->perPage ?? 15);

        // Get current page from request
        $page = $page ?? $this->getCurrentPage($pageName);

        // Ensure page is at least 1
        $page = max(1, $page);

        // Clone the query to preserve the original for counting
        $countQuery = clone $this;
        $total = $countQuery->count();

        // Get results for current page
        $originalColumns = $this->columns;
        $this->columns = $columns;

        $results = $this->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        $this->columns = $originalColumns;

        return new \Core\Support\Paginator(
            $results,
            $total,
            $perPage,
            $page
        );
    }

    /**
     * Get the current page from request
     */
    protected function getCurrentPage(string $pageName): int
    {
        $page = $_GET[$pageName] ?? $_POST[$pageName] ?? 1;

        if (filter_var($page, FILTER_VALIDATE_INT) !== false && (int) $page >= 1) {
            return (int) $page;
        }

        return 1;
    }

    /**
     * Get the maximum value of a column
     */
    public function max(string $column): mixed
    {
        return $this->aggregate('MAX', $column);
    }

    /**
     * Get the minimum value of a column
     */
    public function min(string $column): mixed
    {
        return $this->aggregate('MIN', $column);
    }

    /**
     * Get the sum of a column
     */
    public function sum(string $column): mixed
    {
        return $this->aggregate('SUM', $column);
    }

    /**
     * Get the average value of a column
     */
    public function avg(string $column): mixed
    {
        return $this->aggregate('AVG', $column);
    }

    /**
     * Execute an aggregate function
     */
    public function aggregate(string $function, string $column): mixed
    {
        $originalColumns = $this->columns;
        $this->columns = ["{$function}({$column}) as aggregate"];

        $result = $this->first();

        $this->columns = $originalColumns;

        return $result['aggregate'] ?? null;
    }


    public function pluck(string $value, ?string $key = null): Collection
    {
        $results = $this->select([$value])->get();

        if ($key === null) {
            $items = array_map(fn($row) => $row[$value] ?? null, $results->toArray());
        } else {
            $items = [];
            foreach ($results->toArray() as $row) {
                $items[$row[$key] ?? null] = $row[$value] ?? null;
            }
        }

        return new Collection($items);
    }



    protected function toSelectSql(): string
    {
        $columns = implode(', ', $this->columns);

        $sql = "SELECT {$columns} FROM {$this->table}";

        // Add joins
        $sql .= $this->buildJoinClauses();

        // Add where clauses
        $sql .= $this->buildWhereClauses();

        if ($this->orderByColumn) {
            $sql .= " ORDER BY {$this->orderByColumn} {$this->orderByDirection}";
        }

        if ($this->limitValue !== null) {
            $sql .= " LIMIT {$this->limitValue}";
        }

        if ($this->offsetValue !== null) {
            $sql .= " OFFSET {$this->offsetValue}";
        }

        return $sql;
    }

    /**
     * Build the JOIN clauses for the query
     */
    protected function buildJoinClauses(): string
    {
        if (empty($this->joins)) {
            return '';
        }

        $sql = '';

        foreach ($this->joins as $join) {
            $sql .= " {$join['type']} JOIN {$join['table']}";

            if ($join['type'] !== 'CROSS') {
                $sql .= " ON {$join['first']} {$join['operator']} {$join['second']}";
            }
        }

        return $sql;
    }

    /**
     * Add a where clause that matches ANY of the given columns
     * 
     * @param array $columns Array of column names
     * @param mixed $operator Operator or value if using '='
     * @param mixed|null $value Value to compare (optional if operator is the value)
     * @return self
     * 
     * Examples:
     * whereAny(['name', 'email'], 'LIKE', '%john%')
     * whereAny(['status', 'type'], 'active')
     */
    public function whereAny(array $columns, mixed $operator = null, mixed $value = null): self
    {
        if (empty($columns)) {
            return $this;
        }

        // Handle two-parameter case: whereAny(['col1', 'col2'], 'value')
        if ($value === null && $operator !== null) {
            $value = $operator;
            $operator = '=';
        }

        $this->wheres[] = [
            'type' => 'any',
            'columns' => $columns,
            'operator' => $operator,
            'value' => $value,
            'boolean' => empty($this->wheres) ? '' : 'AND'
        ];

        // Add binding for each column
        foreach ($columns as $column) {
            $this->bindings[] = $value;
        }

        return $this;
    }

    /**
     * Add an OR where clause that matches ANY of the given columns
     * 
     * @param array $columns Array of column names
     * @param mixed $operator Operator or value if using '='
     * @param mixed|null $value Value to compare
     * @return self
     */
    public function orWhereAny(array $columns, mixed $operator = null, mixed $value = null): self
    {
        if (empty($columns)) {
            return $this;
        }

        if ($value === null && $operator !== null) {
            $value = $operator;
            $operator = '=';
        }

        $this->wheres[] = [
            'type' => 'any',
            'columns' => $columns,
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'OR'
        ];

        foreach ($columns as $column) {
            $this->bindings[] = $value;
        }

        return $this;
    }

    /**
     * Add a where clause that matches ALL of the given columns
     * 
     * @param array $columns Array of column names
     * @param mixed $operator Operator or value if using '='
     * @param mixed|null $value Value to compare
     * @return self
     * 
     * Examples:
     * whereAll(['status', 'is_active'], 1)
     * whereAll(['created_by', 'updated_by'], '=', 5)
     */
    public function whereAll(array $columns, mixed $operator = null, mixed $value = null): self
    {
        if (empty($columns)) {
            return $this;
        }

        if ($value === null && $operator !== null) {
            $value = $operator;
            $operator = '=';
        }

        foreach ($columns as $column) {
            $this->where($column, $operator, $value);
        }

        return $this;
    }

    /**
     * Add a where clause for NULL values on any column
     * 
     * @param array $columns Array of column names
     * @return self
     */
    public function whereAnyNull(array $columns): self
    {
        if (empty($columns)) {
            return $this;
        }

        $this->wheres[] = [
            'type' => 'any_null',
            'columns' => $columns,
            'boolean' => empty($this->wheres) ? '' : 'AND'
        ];

        return $this;
    }

    /**
     * Add a where clause for NOT NULL values on any column
     * 
     * @param array $columns Array of column names
     * @return self
     */
    public function whereAnyNotNull(array $columns): self
    {
        if (empty($columns)) {
            return $this;
        }

        $this->wheres[] = [
            'type' => 'any_not_null',
            'columns' => $columns,
            'boolean' => empty($this->wheres) ? '' : 'AND'
        ];

        return $this;
    }

    /**
     * Add a where NULL clause
     * 
     * @param string $column Column name
     * @return self
     */
    public function whereNull(string $column): self
    {
        $this->wheres[] = [
            'type' => 'null',
            'column' => $column,
            'boolean' => empty($this->wheres) ? '' : 'AND'
        ];

        return $this;
    }

    /**
     * Add a where NOT NULL clause
     * 
     * @param string $column Column name
     * @return self
     */
    public function whereNotNull(string $column): self
    {
        $this->wheres[] = [
            'type' => 'not_null',
            'column' => $column,
            'boolean' => empty($this->wheres) ? '' : 'AND'
        ];

        return $this;
    }

    /**
     * Add a where BETWEEN clause
     * 
     * @param string $column Column name
     * @param mixed $min Minimum value
     * @param mixed $max Maximum value
     * @return self
     */
    public function whereBetween(string $column, mixed $min, mixed $max): self
    {
        $this->wheres[] = [
            'type' => 'between',
            'column' => $column,
            'min' => $min,
            'max' => $max,
            'boolean' => empty($this->wheres) ? '' : 'AND'
        ];

        $this->bindings[] = $min;
        $this->bindings[] = $max;

        return $this;
    }

    /**
     * Add a where NOT BETWEEN clause
     * 
     * @param string $column Column name
     * @param mixed $min Minimum value
     * @param mixed $max Maximum value
     * @return self
     */
    public function whereNotBetween(string $column, mixed $min, mixed $max): self
    {
        $this->wheres[] = [
            'type' => 'not_between',
            'column' => $column,
            'min' => $min,
            'max' => $max,
            'boolean' => empty($this->wheres) ? '' : 'AND'
        ];

        $this->bindings[] = $min;
        $this->bindings[] = $max;

        return $this;
    }

    /**
     * Add a where NOT IN clause
     * 
     * @param string $column Column name
     * @param array $values Array of values
     * @return self
     */
    public function whereNotIn(string $column, array $values): self
    {
        $this->wheres[] = [
            'type' => 'not_in',
            'column' => $column,
            'values' => $values,
            'boolean' => empty($this->wheres) ? '' : 'AND'
        ];

        $this->bindings = array_merge($this->bindings, $values);

        return $this;
    }

    // Update the buildWhereClauses method to handle new where types
    protected function buildWhereClauses(): string
    {
        if (empty($this->wheres)) {
            return '';
        }

        $sql = ' WHERE ';
        $clauses = [];

        foreach ($this->wheres as $where) {
            $clause = $where['boolean'] ? "{$where['boolean']} " : '';

            switch ($where['type']) {
                case 'basic':
                    $clause .= "{$where['column']} {$where['operator']} ?";
                    break;

                case 'in':
                    $placeholders = implode(', ', array_fill(0, count($where['values']), '?'));
                    $clause .= "{$where['column']} IN ({$placeholders})";
                    break;

                case 'not_in':
                    $placeholders = implode(', ', array_fill(0, count($where['values']), '?'));
                    $clause .= "{$where['column']} NOT IN ({$placeholders})";
                    break;

                case 'any':
                    // (column1 = ? OR column2 = ? OR column3 = ?)
                    $conditions = [];
                    foreach ($where['columns'] as $column) {
                        $conditions[] = "{$column} {$where['operator']} ?";
                    }
                    $clause .= '(' . implode(' OR ', $conditions) . ')';
                    break;

                case 'any_null':
                    // (column1 IS NULL OR column2 IS NULL)
                    $conditions = [];
                    foreach ($where['columns'] as $column) {
                        $conditions[] = "{$column} IS NULL";
                    }
                    $clause .= '(' . implode(' OR ', $conditions) . ')';
                    break;

                case 'any_not_null':
                    // (column1 IS NOT NULL OR column2 IS NOT NULL)
                    $conditions = [];
                    foreach ($where['columns'] as $column) {
                        $conditions[] = "{$column} IS NOT NULL";
                    }
                    $clause .= '(' . implode(' OR ', $conditions) . ')';
                    break;

                case 'null':
                    $clause .= "{$where['column']} IS NULL";
                    break;

                case 'not_null':
                    $clause .= "{$where['column']} IS NOT NULL";
                    break;

                case 'between':
                    $clause .= "{$where['column']} BETWEEN ? AND ?";
                    break;

                case 'not_between':
                    $clause .= "{$where['column']} NOT BETWEEN ? AND ?";
                    break;
            }

            $clauses[] = $clause;
        }

        $sql .= implode(' ', $clauses);

        return $sql;
    }

    public function toSql(): string
    {
        return $this->toSelectSql();
    }
    /**
     * Handle dynamic method calls for magic where clauses
     * 
     */
    public function __call(string $method, array $parameters): mixed
    {
        if (str_starts_with($method, 'where') && $method !== 'where' && $method !== 'whereIn') {
            return $this->dynamicWhere($method, $parameters, 'where');
        }

        if (str_starts_with($method, 'orWhere') && $method !== 'orWhere') {
            return $this->dynamicWhere($method, $parameters, 'orWhere');
        }

        throw new \BadMethodCallException("Method {$method} does not exist on " . static::class);
    }

    /**
     * Handle dynamic where clause
     */
    protected function dynamicWhere(string $method, array $parameters, string $type): self
    {
        if ($type === 'where') {
            $column = substr($method, 5); 
        } else {
            $column = substr($method, 7); 
        }

        $column = Str::snake($column);

        if (count($parameters) === 1) {
            $operator = '=';
            $value = $parameters[0];
        } elseif (count($parameters) === 2) {
            $operator = $parameters[0];
            $value = $parameters[1];
        } else {
            throw new \InvalidArgumentException("Invalid number of parameters for {$method}");
        }

        if ($type === 'where') {
            return $this->where($column, $operator, $value);
        } else {
            return $this->orWhere($column, $operator, $value);
        }
    }
}
