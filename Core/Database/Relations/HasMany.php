<?php

namespace Core\Database\Relations;

use Core\Database\EloquentBuilder;
use Core\Database\Model;
use Core\Support\Collection;

class HasMany extends Relation
{
    /**
     * The foreign key of the parent model.
     */
    protected string $foreignKey;

    /**
     * The local key of the parent model.
     */
    protected string $localKey;

    /**
     * Create a new has many relationship instance.
     */
    public function __construct(EloquentBuilder $query, Model $parent, string $foreignKey, string $localKey)
    {
        $this->foreignKey = $foreignKey;
        $this->localKey = $localKey;

        parent::__construct($query, $parent);
    }

    /**
     * Set the base constraints on the relation query.
     */
    public function addConstraints(): void
    {
        if (static::$constraints) {
            $this->query->where(
                $this->foreignKey,
                '=',
                $this->getParentKey()
            );
        }
    }

    /**
     * Set the constraints for an eager load of the relation.
     */
    public function addEagerConstraints(array $models): void
    {
        $this->query->whereIn(
            $this->foreignKey,
            $this->getKeys($models, $this->localKey)
        );
    }

    /**
     * Initialize the relation on a set of models.
     */
    public function initRelation(array $models, string $relation): array
    {
        foreach ($models as $model) {
            $model->setRelation($relation, new Collection([]));
        }

        return $models;
    }

    /**
     * Match the eagerly loaded results to their parents.
     */
    public function match(array $models, Collection $results, string $relation): array
    {
        return $this->matchMany($models, $results, $relation);
    }

    /**
     * Match the eagerly loaded results to their many parents.
     */
    protected function matchMany(array $models, Collection $results, string $relation): array
    {
        $dictionary = $this->buildDictionary($results);

        foreach ($models as $model) {
            $key = $model->getAttribute($this->localKey);

            if (isset($dictionary[$key])) {
                $model->setRelation($relation, new Collection($dictionary[$key]));
            }
        }

        return $models;
    }

    /**
     * Build model dictionary keyed by the relation's foreign key.
     */
    protected function buildDictionary(Collection $results): array
    {
        $dictionary = [];

        foreach ($results as $result) {
            $key = $result->getAttribute($this->foreignKey);
            
            if (!isset($dictionary[$key])) {
                $dictionary[$key] = [];
            }
            
            $dictionary[$key][] = $result;
        }

        return $dictionary;
    }

    /**
     * Get the results of the relationship.
     */
    public function getResults(): Collection
    {
        return $this->query->get();
    }

    /**
     * Create a new instance of the related model.
     */
    public function create(array $attributes = []): Model
    {
        $instance = $this->related->newInstance($attributes);
        
        // Automatically set the foreign key
        $instance->setAttribute($this->foreignKey, $this->getParentKey());
        
        $instance->save();

        return $instance;
    }

    /**
     * Create multiple new instances of the related model.
     */
    public function createMany(array $records): Collection
    {
        $instances = [];

        foreach ($records as $attributes) {
            $instances[] = $this->create($attributes);
        }

        return new Collection($instances);
    }

    /**
     * Get the key value of the parent's local key.
     */
    protected function getParentKey(): mixed
    {
        return $this->parent->getAttribute($this->localKey);
    }

    /**
     * Get the foreign key for the relationship.
     */
    public function getForeignKeyName(): string
    {
        return $this->foreignKey;
    }

    /**
     * Get the local key for the relationship.
     */
    public function getLocalKeyName(): string
    {
        return $this->localKey;
    }
}