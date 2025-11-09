<?php

namespace Core\Database\Relations;

use Core\Database\EloquentBuilder;
use Core\Database\Model;
use Core\Support\Collection;

class MorphTo extends Relation
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
     * The models whose relations are being eager loaded.
     */
    protected array $models = [];

    /**
     * All of the models keyed by type.
     */
    protected array $dictionary = [];

    /**
     * Create a new morph to relationship instance.
     */
    public function __construct(EloquentBuilder $query, Model $parent, string $foreignKey, string $morphType)
    {
        $this->foreignKey = $foreignKey;
        $this->morphType = $morphType;

        parent::__construct($query, $parent);
    }

    /**
     * Set the base constraints on the relation query.
     */
    public function addConstraints(): void
    {
        if (static::$constraints) {
            $morphType = $this->parent->getAttribute($this->morphType);
            $foreignKey = $this->parent->getAttribute($this->foreignKey);

            if ($morphType && $foreignKey) {
                $this->query->where($this->related->getKeyName(), '=', $foreignKey);
            }
        }
    }

    /**
     * Set the constraints for an eager load of the relation.
     */
    public function addEagerConstraints(array $models): void
    {
        $this->models = $models;
        $this->buildDictionary($models);
    }

    /**
     * Build a dictionary with the models grouped by type.
     */
    protected function buildDictionary(array $models): void
    {
        foreach ($models as $model) {
            $morphType = $model->getAttribute($this->morphType);
            $foreignKey = $model->getAttribute($this->foreignKey);

            if ($morphType && $foreignKey) {
                $this->dictionary[$morphType][$foreignKey][] = $model;
            }
        }
    }

    /**
     * Get the results of the relationship.
     */
    public function getResults(): ?Model
    {
        $morphType = $this->parent->getAttribute($this->morphType);
        $foreignKey = $this->parent->getAttribute($this->foreignKey);

        if (!$morphType || !$foreignKey) {
            return null;
        }

        // Create instance of the morph type model
        $instance = new $morphType;
        
        return $instance::query()->find($foreignKey);
    }

    /**
     * Get the relationship for eager loading.
     */
    public function getEager(): Collection
    {
        $results = [];

        foreach ($this->dictionary as $type => $models) {
            $ids = array_keys($models);
            
            // Create instance of the morph type model
            $instance = new $type;
            
            // Fetch all models of this type
            $typeResults = $instance::query()->findMany($ids);
            
            foreach ($typeResults as $result) {
                $results[] = $result;
            }
        }

        return new Collection($results);
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
        // Build a dictionary of results keyed by type and id
        $dictionary = [];
        
        foreach ($results as $result) {
            $type = get_class($result);
            $key = $result->getKey();
            $dictionary[$type][$key] = $result;
        }

        // Match the results to their parent models
        foreach ($models as $model) {
            $morphType = $model->getAttribute($this->morphType);
            $foreignKey = $model->getAttribute($this->foreignKey);

            if ($morphType && $foreignKey && isset($dictionary[$morphType][$foreignKey])) {
                $model->setRelation($relation, $dictionary[$morphType][$foreignKey]);
            }
        }

        return $models;
    }

    /**
     * Associate the model instance to the given parent.
     */
    public function associate(?Model $model): Model
    {
        if ($model) {
            $this->parent->setAttribute($this->foreignKey, $model->getKey());
            $this->parent->setAttribute($this->morphType, get_class($model));
        } else {
            $this->parent->setAttribute($this->foreignKey, null);
            $this->parent->setAttribute($this->morphType, null);
        }

        return $this->parent;
    }

    /**
     * Dissociate previously associated model from the given parent.
     */
    public function dissociate(): Model
    {
        $this->parent->setAttribute($this->foreignKey, null);
        $this->parent->setAttribute($this->morphType, null);

        return $this->parent;
    }
}