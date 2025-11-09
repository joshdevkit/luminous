<?php

namespace Core\Database\Relations;

use Core\Database\EloquentBuilder;
use Core\Database\Model;
use Core\Support\Collection;

class BelongsTo extends Relation
{
    /**
     * The foreign key of the parent model.
     */
    protected string $foreignKey;

    /**
     * The associated key on the parent model.
     */
    protected string $ownerKey;

    /**
     * The name of the relationship.
     */
    protected string $relationName;

    /**
     * Create a new belongs to relationship instance.
     */
    public function __construct(EloquentBuilder $query, Model $child, string $foreignKey, string $ownerKey, string $relationName)
    {
        $this->foreignKey = $foreignKey;
        $this->ownerKey = $ownerKey;
        $this->relationName = $relationName;

        parent::__construct($query, $child);
    }

    /**
     * Set the base constraints on the relation query.
     */
    public function addConstraints(): void
    {
        if (static::$constraints) {
            $this->query->where(
                $this->ownerKey,
                '=',
                $this->parent->getAttribute($this->foreignKey)
            );
        }
    }

    /**
     * Set the constraints for an eager load of the relation.
     */
    public function addEagerConstraints(array $models): void
    {
        $keys = $this->getKeys($models, $this->foreignKey);

        $this->query->whereIn($this->ownerKey, $keys);
    }

    /**
     * Initialize the relation on a set of models.
     */
    public function initRelation(array $models, string $relation): array
    {
        foreach ($models as $model) {
            $model->setRelation($relation, null);
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
            $key = $model->getAttribute($this->foreignKey);

            if (isset($dictionary[$key])) {
                $model->setRelation($relation, $dictionary[$key]);
            }
        }

        return $models;
    }

    /**
     * Build model dictionary keyed by the relation's owner key.
     */
    protected function buildDictionary(Collection $results): array
    {
        $dictionary = [];

        foreach ($results as $result) {
            $dictionary[$result->getAttribute($this->ownerKey)] = $result;
        }

        return $dictionary;
    }

    /**
     * Get the results of the relationship.
     */
    public function getResults(): ?Model
    {
        return $this->query->first();
    }

    /**
     * Create a new instance of the related model and associate it.
     */
    public function create(array $attributes = []): Model
    {
        $instance = $this->related->newInstance($attributes);
        
        $instance->save();
        
        // Associate the newly created model
        $this->associate($instance);
        
        // Save the parent to persist the foreign key
        $this->parent->save();

        return $instance;
    }

    /**
     * Associate the model instance to the given parent.
     */
    public function associate(?Model $model): Model
    {
        $this->parent->setAttribute(
            $this->foreignKey,
            $model?->getAttribute($this->ownerKey)
        );

        if ($model) {
            $this->parent->setRelation($this->relationName, $model);
        } else {
            $this->parent->unsetRelation($this->relationName);
        }

        return $this->parent;
    }

    /**
     * Dissociate previously associated model from the given parent.
     */
    public function dissociate(): Model
    {
        $this->parent->setAttribute($this->foreignKey, null);
        $this->parent->setRelation($this->relationName, null);

        return $this->parent;
    }

    /**
     * Get the foreign key for the relationship.
     */
    public function getForeignKeyName(): string
    {
        return $this->foreignKey;
    }

    /**
     * Get the owner key for the relationship.
     */
    public function getOwnerKeyName(): string
    {
        return $this->ownerKey;
    }
}