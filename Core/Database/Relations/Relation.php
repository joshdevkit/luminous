<?php

namespace Core\Database\Relations;

use Core\Database\EloquentBuilder;
use Core\Database\Model;
use Core\Support\Collection;

abstract class Relation
{
    /**
     * The parent model instance.
     */
    protected Model $parent;

    /**
     * The related model instance.
     */
    protected Model $related;

    /**
     * The Eloquent query builder instance.
     */
    protected EloquentBuilder $query;

    /**
     * Indicates if the relation is adding constraints.
     */
    protected static bool $constraints = true;

    /**
     * Create a new relation instance.
     */
    public function __construct(EloquentBuilder $query, Model $parent)
    {
        $this->query = $query;
        $this->parent = $parent;
        $this->related = $query->getModel();

        $this->addConstraints();
    }

    /**
     * Set the base constraints on the relation query.
     */
    abstract public function addConstraints(): void;

    /**
     * Set the constraints for an eager load of the relation.
     */
    abstract public function addEagerConstraints(array $models): void;

    /**
     * Initialize the relation on a set of models.
     */
    abstract public function initRelation(array $models, string $relation): array;

    /**
     * Match the eagerly loaded results to their parents.
     */
    abstract public function match(array $models, Collection $results, string $relation): array;

    /**
     * Get the results of the relationship.
     */
    abstract public function getResults(): mixed;

    /**
     * Get the relationship for eager loading.
     */
    public function getEager(): Collection
    {
        return $this->get();
    }

    /**
     * Execute the query as a "select" statement.
     */
    public function get(array $columns = ['*']): Collection
    {
        return $this->query->get($columns);
    }

    /**
     * Run a callback with constraints disabled on the relation.
     */
    public static function noConstraints(callable $callback): mixed
    {
        $previous = static::$constraints;
        static::$constraints = false;

        try {
            return $callback();
        } finally {
            static::$constraints = $previous;
        }
    }

    /**
     * Get all of the primary keys for an array of models.
     */
    protected function getKeys(array $models, ?string $key = null): array
    {
        return array_unique(array_values(array_map(function ($model) use ($key) {
            return $key ? $model->getAttribute($key) : $model->getKey();
        }, $models)));
    }
    
    /**
     * Get the underlying query for the relation.
     */
    public function getQuery(): EloquentBuilder
    {
        return $this->query;
    }

    /**
     * Get the parent model of the relation.
     */
    public function getParent(): Model
    {
        return $this->parent;
    }

    /**
     * Get the related model of the relation.
     */
    public function getRelated(): Model
    {
        return $this->related;
    }

    /**
     * Handle dynamic method calls to the relationship.
     */
    public function __call(string $method, array $parameters)
    {
        $result = $this->query->$method(...$parameters);

        if ($result === $this->query) {
            return $this;
        }

        return $result;
    }
}