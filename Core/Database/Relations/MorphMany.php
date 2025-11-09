<?php

namespace Core\Database\Relations;

use Core\Database\EloquentBuilder;
use Core\Database\Model;
use Core\Support\Collection;

class MorphMany extends Relation
{
    /**
     * The foreign key of the parent model.
     */
    protected string $foreignKey;

    /**
     * The "type" key for the polymorphic relation.
     */
    protected string $morphType;

    /**
     * The class name of the parent model.
     */
    protected string $morphClass;

    /**
     * Create a new morph many relationship instance.
     */
    public function __construct(EloquentBuilder $query, Model $parent, string $morphType, string $foreignKey, string $localKey)
    {
        $this->foreignKey = $foreignKey;
        $this->morphType = $morphType;
        $this->morphClass = get_class($parent);

        parent::__construct($query, $parent);
    }

    /**
     * Set the base constraints on the relation query.
     */
    public function addConstraints(): void
    {
        if (static::$constraints) {
            $this->query->where($this->foreignKey, '=', $this->parent->getKey());
            $this->query->where($this->morphType, '=', $this->morphClass);
        }
    }

    /**
     * Set the constraints for an eager load of the relation.
     */
    public function addEagerConstraints(array $models): void
    {
        $this->query->whereIn(
            $this->foreignKey,
            $this->getKeys($models)
        );

        $this->query->where($this->morphType, '=', $this->morphClass);
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
        $dictionary = $this->buildDictionary($results);

        foreach ($models as $model) {
            $key = $model->getKey();
            
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
        
        $instance->setAttribute($this->foreignKey, $this->parent->getKey());
        $instance->setAttribute($this->morphType, $this->morphClass);
        
        $instance->save();

        return $instance;
    }

    /**
     * Get the foreign key for the relationship.
     */
    public function getForeignKeyName(): string
    {
        return $this->foreignKey;
    }

    /**
     * Get the morph type for the relationship.
     */
    public function getMorphType(): string
    {
        return $this->morphType;
    }
}