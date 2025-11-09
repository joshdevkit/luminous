<?php

namespace Core\Database;

use Core\Database\Relations\Relation;
use Core\Support\Collection;
use Exception;

class EloquentBuilder
{
    protected QueryBuilder $query;
    protected Model $model;
    protected array $eagerLoad = [];

    public function __construct(QueryBuilder $query, Model $model)
    {
        $this->query = $query;
        $this->model = $model;
    }

    /**
     * Get the model instance.
     */
    public function getModel(): Model
    {
        return $this->model;
    }

    /**
     * Get the underlying query builder.
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * Create a new model instance and save it to the database.
     */
    public function create(array $attributes): Model
    {
        return tap($this->newModelInstance($attributes), function ($instance) {
            $instance->save();
        });
    }

    /**
     * Create a new instance of the model being queried.
     */
    public function newModelInstance(array $attributes = []): Model
    {
        $instance = $this->model->newInstance($attributes);

        // Maintain the connection name from the query
        if ($connectionName = $this->model->getConnectionName()) {
            $instance->setConnection($connectionName);
        }

        return $instance;
    }

    /**
     * Create a new model instance and save it to the database, bypassing mass assignment.
     */
    public function forceCreate(array $attributes): Model
    {
        return $this->model->unguarded(function () use ($attributes) {
            return $this->create($attributes);
        });
    }

    /**
     * Set the relationships that should be eager loaded.
     */
    public function with(string|array $relations): self
    {
        if (is_string($relations)) {
            $relations = func_get_args();
        }

        $eagerLoad = $this->parseWithRelations($relations);

        // Validate each relation immediately
        foreach (array_keys($eagerLoad) as $relation) {
            // For nested relations, only validate the first segment
            $relationName = explode('.', $relation)[0];

            // Try to call the method and check if it returns a Relation
            try {
                $result = Relation::noConstraints(function () use ($relationName) {
                    return $this->getModel()->$relationName();
                });

                // If the result is not a Relation instance, the method doesn't exist
                // or doesn't return a relationship (likely forwarded to query builder via __call)
                if (!$result instanceof Relation) {
                    throw new Exception(sprintf(
                        'Call to undefined relationship [%s] on model [%s].',
                        $relationName,
                        get_class($this->getModel())
                    ));
                }
            } catch (\Throwable $e) {
                // Re-throw if it's already our exception
                if ($e instanceof Exception && str_contains($e->getMessage(), 'undefined relationship')) {
                    throw $e;
                }

                // Otherwise, wrap it
                throw new Exception(sprintf(
                    'Call to undefined relationship [%s] on model [%s].',
                    $relationName,
                    get_class($this->getModel())
                ));
            }
        }

        $this->eagerLoad = array_merge($this->eagerLoad, $eagerLoad);

        // Add this line to update the model's with property
        $this->model->setWith(array_keys($this->eagerLoad));

        return $this;
    }

    /**
     * Parse a list of relations into individuals.
     */
    protected function parseWithRelations(array $relations): array
    {
        $results = [];

        foreach ($relations as $name => $constraints) {
            if (is_numeric($name)) {
                $name = $constraints;
                $constraints = null;
            }

            $results[$name] = $constraints;
        }

        return $results;
    }

    /**
     * Get the models without eager loading.
     */
    public function getModels(array $columns = ['*']): array
    {
        return $this->model->hydrate(
            $this->query->get($columns),
            $this->model
        );
    }

    /**
     * Eager load the relationships for the models.
     */
    public function eagerLoadRelations(array $models): array
    {
        foreach ($this->eagerLoad as $name => $constraints) {
            // Handle both simple and nested relations
            if (str_contains($name, '.')) {
                $models = $this->eagerLoadNestedRelation($models, $name, $constraints);
            } else {
                $models = $this->eagerLoadRelation($models, $name, $constraints);
            }
        }

        return $models;
    }

    /**
     * Eagerly load a nested relationship.
     */
    protected function eagerLoadNestedRelation(array $models, string $name, ?callable $constraints): array
    {
        // Split the nested relation into segments (e.g., "first.inner" => ["first", "inner"])
        $segments = explode('.', $name);
        $firstSegment = array_shift($segments);
        $remaining = implode('.', $segments);

        // Load the first level relation if not already loaded
        if (!isset($this->eagerLoad[$firstSegment])) {
            $models = $this->eagerLoadRelation($models, $firstSegment, null);
        }

        // Collect all related models from the first segment
        $relatedModels = [];
        foreach ($models as $model) {
            // Access relations through getRelations() method
            $relations = $model->getRelations();

            if (isset($relations[$firstSegment])) {
                $relation = $relations[$firstSegment];

                if ($relation instanceof Collection) {
                    foreach ($relation as $relatedModel) {
                        $relatedModels[] = $relatedModel;
                    }
                } elseif ($relation instanceof Model) {
                    $relatedModels[] = $relation;
                }
            }
        }

        // If we have related models, eager load the next level
        if (!empty($relatedModels)) {
            // Eager load recursively using the same pattern as the parent
            $this->eagerLoadRelationOnModels($relatedModels, $remaining, $constraints);
        }

        return $models;
    }

    /**
     * Helper method to eager load a relation on a set of models.
     */
    protected function eagerLoadRelationOnModels(array $models, string $name, ?callable $constraints): void
    {
        if (empty($models)) {
            return;
        }

        // Get the class of the first model
        $modelClass = get_class($models[0]);

        // Create a temporary instance to get the relation
        $tempInstance = new $modelClass;

        // Get the relation instance using Relation::noConstraints
        $relation = Relation::noConstraints(function () use ($tempInstance, $name) {
            return $tempInstance->$name();
        });

        // Add eager constraints for all models
        $relation->addEagerConstraints($models);

        // Apply custom constraints if provided
        if ($constraints) {
            $constraints($relation);
        }

        // Get the eager results
        $results = $relation->getEager();

        // Initialize relations on all models (set to null by default)
        $relation->initRelation($models, $name);

        // Match the results back to the models
        $relation->match($models, $results, $name);
    }

    /**
     * Eagerly load the relationship on a set of models.
     */
    protected function eagerLoadRelation(array $models, string $name, ?callable $constraints): array
    {
        // First check if models array is empty
        if (empty($models)) {
            return $models;
        }

        $relation = $this->getRelation($name);

        $relation->addEagerConstraints($models);

        if ($constraints) {
            $constraints($relation);
        }

        return $relation->match(
            $relation->initRelation($models, $name),
            $relation->getEager(),
            $name
        );
    }

    /**
     * Get the relation instance for the given relation name.
     */
    public function getRelation(string $name): Relation
    {
        // Try to call the method and check if it returns a Relation
        try {
            $result = Relation::noConstraints(function () use ($name) {
                return $this->getModel()->$name();
            });

            // If the result is not a Relation instance, it means the method doesn't exist
            // or doesn't return a relationship (likely forwarded to query builder via __call)
            if (!$result instanceof Relation) {
                throw new Exception(sprintf(
                    'Call to undefined relationship [%s] on model [%s].',
                    $name,
                    get_class($this->getModel())
                ));
            }

            return $result;
        } catch (\Throwable $e) {
            // Re-throw if it's already our exception
            if ($e instanceof Exception && str_contains($e->getMessage(), 'undefined relationship')) {
                throw $e;
            }

            // Otherwise, wrap it
            throw new Exception(sprintf(
                'Call to undefined relationship [%s] on model [%s].',
                $name,
                get_class($this->getModel())
            ));
        }
    }

    public function get(array $columns = ['*']): Collection
    {
        // Get the base models
        $models = $this->getModels($columns);

        // If we have eager load relations, load them
        if (count($models) > 0 && !empty($this->eagerLoad)) {
            $models = $this->eagerLoadRelations($models);
        }

        return new Collection($models);
    }

    public function first(): ?Model
    {
        $result = $this->query->firstOrFail();
        return $result ? $this->model->newInstance($result, true) : null;
    }

    public function firstOrFail(): Model
    {
        $result = $this->first();

        if ($result === null) {
            $tableName = $this->model->getTable();
            $primaryKey = $this->model->getKeyName();

            $value = null;
            $wheres = $this->query->getWheres();
            if (!empty($wheres)) {
                $lastWhere = end($wheres);
                $value = $lastWhere['value'] ?? json_encode($lastWhere['values'] ?? []);
            }

            throw new \RuntimeException(
                "No query results for query table [{$tableName}] with [{$primaryKey}] value [{$value}]"
            );
        }

        return $result;
    }

    public function find(mixed $id): ?Model
    {
        $this->where($this->model->getKeyName(), $id);

        // Get with eager loading support
        $results = $this->get();

        return $results->first();
    }

    /**
     * Find multiple models by their primary keys.
     */
    public function findMany(array $ids, array $columns = ['*']): Collection
    {
        if (empty($ids)) {
            return new Collection([]);
        }

        return $this->whereIn($this->model->getKeyName(), $ids)->get($columns);
    }

    public function findOrFail(mixed $id): Model
    {
        $result = $this->find($id);

        if ($result === null) {
            $tableName = $this->model->getTable();
            $primaryKey = $this->model->getKeyName();
            throw new \RuntimeException(
                "No query results for table [{$tableName}] with {$primaryKey} value [{$id}]"
            );
        }

        return $result;
    }

    /**
     * Get the count of the total records.
     */
    public function count(string $columns = '*'): int
    {
        return $this->query->count($columns);
    }

    /**
     * Determine if any rows exist for the current query.
     */
    public function exists(): bool
    {
        return $this->count() > 0;
    }

    public function insert(array $values): bool
    {
        return $this->query->insert($values);
    }

    public function update(array $values): int
    {
        return $this->query->update($values);
    }

    public function delete(): int
    {
        return $this->query->delete();
    }

    public function where(string $column, mixed $operator = null, mixed $value = null): self
    {
        $this->query->where($column, $operator, $value);
        return $this;
    }

    public function orWhere(string $column, mixed $operator = null, mixed $value = null): self
    {
        $this->query->orWhere($column, $operator, $value);
        return $this;
    }

    public function whereIn(string $column, array $values): self
    {
        $this->query->whereIn($column, $values);
        return $this;
    }

    public function orderBy(string $column, string $direction = 'asc'): self
    {
        $this->query->orderBy($column, $direction);
        return $this;
    }

    public function limit(int $limit): self
    {
        $this->query->limit($limit);
        return $this;
    }

    public function offset(int $offset): self
    {
        $this->query->offset($offset);
        return $this;
    }

    public function select(array|string $columns = ['*']): self
    {
        $this->query->select($columns);
        return $this;
    }

    /**
     * Paginate the query results
     */
    public function paginate(?int $perPage = null, array $columns = ['*'], string $pageName = 'page', ?int $page = null): \Core\Support\Paginator
    {
        $perPage = $perPage ?? ($this->model->perPage ?? 15);
        $page = $page ?? $this->getCurrentPage($pageName);
        $page = max(1, $page);

        // Get total count
        $total = $this->query->count();

        // Get results for current page
        $results = $this->query
            ->select($columns)
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        // Hydrate into models
        $models = Model::hydrate($results, $this->model);

        return new \Core\Support\Paginator(
            new Collection($models),
            $total,
            $perPage,
            $page
        );
    }

    protected function getCurrentPage(string $pageName): int
    {
        $page = $_GET[$pageName] ?? $_POST[$pageName] ?? 1;

        if (filter_var($page, FILTER_VALIDATE_INT) !== false && (int) $page >= 1) {
            return (int) $page;
        }

        return 1;
    }

    /**
     * Chunk the results of the query.
     */
    public function chunk(int $count, callable $callback): bool
    {
        $page = 1;

        do {
            $results = $this->forPage($page, $count)->get();

            $countResults = $results->count();

            if ($countResults == 0) {
                break;
            }

            if ($callback($results, $page) === false) {
                return false;
            }

            $page++;
        } while ($countResults == $count);

        return true;
    }

    public function on(?string $connection): self
    {
        $this->model->setConnection($connection);

        // Recreate the query with the new connection
        $newConnection = Capsule::connection($connection);
        $this->query = $newConnection->table($this->model->getTable(), $this->model);

        return $this;
    }

    /**
     * Set the limit and offset for a given page.
     */
    public function forPage(int $page, int $perPage = 15): self
    {
        return $this->offset(($page - 1) * $perPage)->limit($perPage);
    }

    public function toSql(): string
    {
        return $this->query->toSql();
    }

    public function __call(string $method, array $parameters)
    {
        $result = $this->query->$method(...$parameters);

        if ($result === $this->query) {
            return $this;
        }

        return $result;
    }
}
