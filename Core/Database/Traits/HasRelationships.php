<?php

namespace Core\Database\Traits;

use Core\Database\Relations\BelongsTo;
use Core\Database\Relations\BelongsToMany;
use Core\Database\Relations\HasMany;
use Core\Database\Relations\HasOne;
use Core\Database\Relations\MorphMany;
use Core\Database\Relations\MorphTo;
use Core\Database\Relations\Relation;
use Core\Support\Str;

trait HasRelationships
{
    /**
     * The loaded relationships for the model.
     */
    protected array $relations = [];

    /**
     * The relationships that should be eager loaded.
     */
    protected $with = [];

    /**
     * Define a one-to-one relationship.
     */
    public function hasOne(string $related, ?string $foreignKey = null, ?string $localKey = null): HasOne
    {
        $instance = new $related;

        $foreignKey = $foreignKey ?: $this->getForeignKey();
        $localKey = $localKey ?: $this->getKeyName();

        return new HasOne($instance->newQuery(), $this, $foreignKey, $localKey);
    }

    /**
     * Define a one-to-many relationship.
     */
    public function hasMany(string $related, ?string $foreignKey = null, ?string $localKey = null): HasMany
    {
        $instance = new $related;

        $foreignKey = $foreignKey ?: $this->getForeignKey();
        $localKey = $localKey ?: $this->getKeyName();

        return new HasMany($instance->newQuery(), $this, $foreignKey, $localKey);
    }

    /**
     * Define an inverse one-to-one or many relationship.
     */
    public function belongsTo(string $related, ?string $foreignKey = null, ?string $ownerKey = null, ?string $relation = null): BelongsTo
    {
        if (is_null($relation)) {
            $relation = $this->guessBelongsToRelation();
        }

        $instance = new $related;

        $foreignKey = $foreignKey ?: Str::snake($relation) . '_' . $instance->getKeyName();
        $ownerKey = $ownerKey ?: $instance->getKeyName();

        return new BelongsTo($instance->newQuery(), $this, $foreignKey, $ownerKey, $relation);
    }

    /**
     * Define a many-to-many relationship.
     */
    public function belongsToMany(
        string $related,
        ?string $table = null,
        ?string $foreignPivotKey = null,
        ?string $relatedPivotKey = null,
        ?string $parentKey = null,
        ?string $relatedKey = null
    ): BelongsToMany {
        $instance = new $related;

        $foreignPivotKey = $foreignPivotKey ?: $this->getForeignKey();
        $relatedPivotKey = $relatedPivotKey ?: $instance->getForeignKey();

        if (is_null($table)) {
            $table = $this->joiningTable($related);
        }

        $parentKey = $parentKey ?: $this->getKeyName();
        $relatedKey = $relatedKey ?: $instance->getKeyName();

        return new BelongsToMany(
            $instance->newQuery(),
            $this,
            $table,
            $foreignPivotKey,
            $relatedPivotKey,
            $parentKey,
            $relatedKey
        );
    }


    /**
     * Define a polymorphic one-to-many relationship.
     * 
     * @param string $related The related model class
     * @param string $name The name of the polymorphic relation (e.g., 'commentable')
     * @param string|null $type The morph type column (defaults to {name}_type)
     * @param string|null $id The morph id column (defaults to {name}_id)
     * @param string|null $localKey The local key (defaults to primary key)
     */
    public function morphMany(
        string $related,
        string $name,
        ?string $type = null,
        ?string $id = null,
        ?string $localKey = null
    ): MorphMany {
        $instance = new $related;

        $type = $type ?: $name . '_type';
        $id = $id ?: $name . '_id';
        $localKey = $localKey ?: $this->getKeyName();

        $query = $instance::query();

        return new MorphMany($query, $this, $type, $id, $localKey);
    }

    /**
     * Define a polymorphic inverse relationship.
     * 
     * @param string|null $name The name of the polymorphic relation 
     * @param string|null $type The morph type column (defaults to {name}_type)
     * @param string|null $id The morph id column (defaults to {name}_id)
     */
    public function morphTo(?string $name = null, ?string $type = null, ?string $id = null): MorphTo
    {
        // If name is not provided, try to guess it from the calling method
        if (!$name) {
            $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
            $name = $backtrace[1]['function'] ?? 'morphable';
        }

        $type = $type ?: $name . '_type';
        $id = $id ?: $name . '_id';

        // Get the class from the type column
        $class = $this->getAttribute($type);

        // If we have a class, create a query for it
        if ($class) {
            $instance = new $class;
            $query = $instance::query();
        } else {
            // Create a dummy query (won't be used if no type is set)
            $query = static::query();
        }

        return new MorphTo($query, $this, $id, $type);
    }

    /**
     * Get the joining table name for a many-to-many relation.
     */
    protected function joiningTable(string $related): string
    {
        $models = [
            Str::snake(class_basename($this)),
            Str::snake(class_basename($related)),
        ];

        sort($models);

        return strtolower(implode('_', $models));
    }

    /**
     * Get the default foreign key name for the model.
     */
    public function getForeignKey(): string
    {
        return Str::snake(class_basename($this)) . '_' . $this->getKeyName();
    }

    /**
     * Guess the "belongs to" relationship name.
     */
    protected function guessBelongsToRelation(): string
    {
        [$one, $two, $caller] = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);

        return $caller['function'];
    }

    /**
     * Get a relationship value from a method.
     */
    public function getRelationValue(string $key): mixed
    {
        if ($this->relationLoaded($key)) {
            return $this->relations[$key];
        }

        if (method_exists($this, $key)) {
            return $this->getRelationshipFromMethod($key);
        }

        return null;
    }

    /**
     * Get a relationship from a method.
     */
    protected function getRelationshipFromMethod(string $method): mixed
    {
        $relation = $this->$method();

        if (!$relation instanceof Relation) {
            throw new \LogicException(sprintf(
                '%s::%s must return a relationship instance.',
                static::class,
                $method
            ));
        }

        return tap($relation->getResults(), function ($results) use ($method) {
            $this->setRelation($method, $results);
        });
    }

    /**
     * Determine if the given relation is loaded.
     */
    public function relationLoaded(string $key): bool
    {
        return array_key_exists($key, $this->relations);
    }

    /**
     * Set the given relationship on the model.
     */
    public function setRelation(string $relation, mixed $value): self
    {
        $this->relations[$relation] = $value;

        return $this;
    }

    /**
     * Unset a loaded relationship.
     */
    public function unsetRelation(string $relation): self
    {
        unset($this->relations[$relation]);

        return $this;
    }

    /**
     * Get all the loaded relations for the instance.
     */
    public function getRelations(): array
    {
        return $this->relations;
    }

    /**
     * Set the entire relations array on the model.
     */
    public function setRelations(array $relations): self
    {
        $this->relations = $relations;

        return $this;
    }

    /**
     * Get the relationships that should be eager loaded.
     */
    public function getWith(): array
    {
        return $this->with;
    }

    /**
     * Set the relationships that should be eager loaded.
     */
    public function setWith(array $with): self
    {
        $this->with = $with;

        return $this;
    }
}
