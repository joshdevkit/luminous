<?php

namespace Core\Database;

use Core\Database\Traits\HasAttributes;
use Core\Database\Traits\HasCasting;
use Core\Database\Traits\HasTimestamps;
use Core\Database\Traits\HidesAttributes;
use Core\Database\Traits\GuardsAttributes;
use Core\Database\Traits\HasRelationships;
use Core\Support\Collection;
use Core\Support\Str;

abstract class Model implements \JsonSerializable
{
    use
        HasRelationships,
        HasAttributes,
        HasCasting,
        HasTimestamps,
        HidesAttributes,
        GuardsAttributes;


    protected $table;
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    protected $entities = [];
    protected $guarded = ['*'];
    protected $attributes = [];
    protected $original = [];
    protected $exists = false;
    protected static $unguarded = false;
    protected $hidden = [];
    protected $visible = [];
    protected $casts = [];
    protected $dates = [];
    protected $appends = [];
    protected $with = [];
    public $timestamps = true;
    protected $dateFormat = 'Y-m-d H:i:s';
    protected $perPage  = 15;
    protected $connection;

    protected static $booted = [];

    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    public function __construct(array $attributes = [])
    {
        $this->bootIfNotBooted();
        $this->syncOriginal();
        $this->fill($attributes);
        $this->setTable();
    }

    /**
     * Check if the model needs to be booted and if so, do it.
     */
    protected function bootIfNotBooted(): void
    {
        if (!isset(static::$booted[static::class])) {
            static::$booted[static::class] = true;
            static::boot();
        }
    }

    /**
     * Bootstrap the model and its traits.
     */
    protected static function boot(): void
    {
        static::bootTraits();
    }

    /**
     * Boot all of the bootable traits on the model.
     */
    protected static function bootTraits(): void
    {
        $class = static::class;

        foreach (class_uses_recursive($class) as $trait) {
            $method = 'boot' . class_basename($trait);

            if (method_exists($class, $method)) {
                forward_static_call([$class, $method]);
            }
        }
    }

    /**
     * Fire the given event for the model.
     */
    protected function fireModelEvent(string $event): void
    {
        $method = $event;

        if (method_exists(static::class, $method)) {
            static::$method($this);
        }
    }

    /**
     * Register a creating model event callback.
     */
    protected static function creating(callable $callback): void
    {
        static::registerModelEvent('creating', $callback);
    }

    /**
     * Register a model event callback.
     */
    protected static function registerModelEvent(string $event, callable $callback): void
    {
        if (!isset(static::$booted[static::class . '_' . $event])) {
            static::$booted[static::class . '_' . $event] = [];
        }

        static::$booted[static::class . '_' . $event][] = $callback;
    }

    /**
     * Fire a model event.
     */
    protected static function fireEvent(string $event, $model): void
    {
        $key = static::class . '_' . $event;

        if (isset(static::$booted[$key])) {
            foreach (static::$booted[$key] as $callback) {
                $callback($model);
            }
        }
    }

    /**
     * Get the table name
     */
    public function getTable(): string
    {
        if (isset($this->table)) {
            return $this->table;
        }

        // Get class name without namespace
        $className = class_basename(static::class);

        // Convert to snake_case and pluralize
        return Str::plural(Str::snake($className));
    }


    public function setTable()
    {
        return  $this->table = $this->getTable();
    }

    protected static function newQuery(): EloquentBuilder
    {
        $model = new static;

        // Get connection - use model's connection or default
        $connection = Capsule::connection($model->getConnectionName());

        $query = $connection->table($model->getTable(), $model);
        return new EloquentBuilder($query, $model);
    }


    public static function query(): EloquentBuilder
    {
        return static::newQuery();
    }

    public static function all(array $columns = ['*']): Collection
    {
        return static::query()->get($columns);
    }

    public static function hydrate(array|Collection $items, Model $model): array
    {
        // Convert Collection to array if needed
        if ($items instanceof Collection) {
            $items = $items->all();
        }

        $models = [];

        foreach ($items as $item) {
            $instance = clone $model;

            // Use unguarded to allow all attributes from database
            $instance->unguarded(function () use ($instance, $item) {
                $instance->fill((array) $item);
            });

            $instance->exists = true;
            $instance->syncOriginal();

            $models[] = $instance;
        }

        return $models;
    }

    protected function insertGetId(array $attributes): int|string
    {
        $connection = Capsule::connection();
        $connection->table($this->getTable())->insert($attributes);

        if ($this->incrementing) {
            $id = $connection->getPdo()->lastInsertId();
            return $this->keyType === 'int' ? (int) $id : $id;
        }

        // For non-incrementing keys, return the key from attributes
        // It should already be set by the creating event (e.g., HasUuid trait)
        if (!isset($attributes[$this->primaryKey])) {
            throw new \RuntimeException(
                "Primary key [{$this->primaryKey}] is not set for non-incrementing model. " .
                    "Make sure to set it manually or use a trait like HasUuid."
            );
        }

        return $attributes[$this->primaryKey];
    }


    public function save(): bool
    {
        // Fire creating event for new models BEFORE timestamps
        // This ensures UUID is generated first
        if (!$this->exists) {
            static::fireEvent('creating', $this);
        }

        // Add timestamps
        if ($this->timestamps) {
            $this->updateTimestamps();
        }

        $query = Capsule::table($this->getTable());

        if ($this->exists) {
            // Update
            if (!$this->isDirty()) {
                return true;
            }

            $id = $this->getAttribute($this->primaryKey);
            $dirty = $this->getDirty();
            $result = $query->where($this->primaryKey, $id)->update($dirty) > 0;

            if ($result) {
                $this->syncOriginal();
            }

            return $result;
        }

        // Insert
        $id = $this->insertGetId($this->attributes);

        // Set the primary key attribute if it's not already set
        if (!isset($this->attributes[$this->primaryKey])) {
            $this->attributes[$this->primaryKey] = $id;
        }

        // Force ID to be the first key
        $this->attributes = [$this->primaryKey => $this->attributes[$this->primaryKey]] + $this->attributes;
        $this->exists = true;
        $this->syncOriginal();

        return true;
    }

    public function update(array $attributes): bool
    {
        if (!$this->exists) {
            return false;
        }

        $this->fill($attributes);
        return $this->save();
    }

    public function delete(): bool
    {
        if (!$this->exists) {
            return false;
        }

        $id = $this->getAttribute($this->primaryKey);
        $query = Capsule::table($this->getTable());
        $deleted = $query->where($this->primaryKey, '=', $id)->delete() > 0;

        if ($deleted) {
            $this->exists = false;
        }

        return $deleted;
    }


    public function newInstance(array $attributes, bool $exists = false): static
    {
        $model = new static;

        // When creating from database, bypass mass assignment protection
        if ($exists) {
            $model->unguarded(function () use ($model, $attributes) {
                $model->fill($attributes);
            });
        } else {
            $model->fill($attributes);
        }

        $model->exists = $exists;

        if ($exists) {
            $model->syncOriginal();
        }

        return $model;
    }

    public function replicate(?array $except = null): static
    {
        $except = $except ?: [
            $this->primaryKey,
            static::CREATED_AT,
            static::UPDATED_AT,
        ];

        $attributes = array_diff_key($this->attributes, array_flip($except));

        return new static($attributes);
    }

    protected function syncOriginal(): void
    {
        $this->original = $this->attributes;
    }

    public function isDirty(string|array|null $attributes = null): bool
    {
        $dirty = $this->getDirty();

        if (is_null($attributes)) {
            return count($dirty) > 0;
        }

        $attributes = is_array($attributes) ? $attributes : func_get_args();

        foreach ($attributes as $attribute) {
            if (array_key_exists($attribute, $dirty)) {
                return true;
            }
        }

        return false;
    }

    public function getDirty(): array
    {
        $dirty = [];

        foreach ($this->attributes as $key => $value) {
            if (!array_key_exists($key, $this->original)) {
                $dirty[$key] = $value;
            } elseif ($value !== $this->original[$key]) {
                $dirty[$key] = $value;
            }
        }

        return $dirty;
    }

    public function getOriginal(?string $key = null, mixed $default = null): mixed
    {
        if ($key) {
            return $this->original[$key] ?? $default;
        }

        return $this->original;
    }

    public function toArray(): array
    {
        $attributes = $this->getArrayableAttributes();
        $relations = $this->getArrayableRelations();

        return array_merge($attributes, $relations);
    }


    /**
     * Get an array of all arrayable relations.
     */
    protected function getArrayableRelations(): array
    {
        return array_map(function ($relation) {
            if ($relation instanceof Collection) {
                return $relation->map(function ($item) {
                    return $item instanceof Model ? $item->toArray() : $item;
                })->all();
            }

            return $relation instanceof Model ? $relation->toArray() : $relation;
        }, $this->relations);
    }

    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }

    #[\ReturnTypeWillChange]
    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    public function __toString(): string
    {
        return $this->toJson();
    }

    public function getKey(): mixed
    {
        return $this->getAttribute($this->primaryKey);
    }

    public function getKeyName(): string
    {
        return $this->primaryKey;
    }

    public function getPrimaryKey()
    {
        return $this->primaryKey;
    }

    public function getIncrementing(): bool
    {
        return $this->incrementing;
    }

    public function setIncrementing(bool $value): static
    {
        $this->incrementing = $value;
        return $this;
    }

    public function getKeyType(): string
    {
        return $this->keyType;
    }

    public function setKeyType(string $type): static
    {
        $this->keyType = $type;
        return $this;
    }

    /**
     * Get the casts array with automatic timestamp casting
     */
    public function getCasts(): array
    {
        $casts = $this->casts ?? [];

        // Automatically cast timestamp columns to datetime if timestamps are enabled
        if ($this->timestamps) {
            if (!isset($casts[static::CREATED_AT])) {
                $casts[static::CREATED_AT] = 'datetime';
            }
            if (!isset($casts[static::UPDATED_AT])) {
                $casts[static::UPDATED_AT] = 'datetime';
            }
        }

        // Also cast any columns in $dates array
        foreach ($this->dates ?? [] as $date) {
            if (!isset($casts[$date])) {
                $casts[$date] = 'datetime';
            }
        }

        return $casts;
    }

    /**
     * Get the database connection for the model.
     */
    public function getConnection(): ?string
    {
        return $this->connection;
    }

    /**
     * Set the connection associated with the model.
     */
    public function setConnection(?string $name): static
    {
        $this->connection = $name;
        return $this;
    }

    /**
     * Get the database connection name.
     */
    public function getConnectionName(): ?string
    {
        return $this->connection;
    }


    /**
     * Dynamically retrieve attributes or relationships.
     */
    public function __get(string $key)
    {
        // Try to get from attributes first
        if ($this->hasAttribute($key)) {
            return $this->getAttribute($key);
        }

        // Then try relationships
        return $this->getRelationValue($key);
    }


    /**
     * Eager load relations on the model.
     */
    public function load(string|array $relations): static
    {
        $relations = is_array($relations) ? $relations : func_get_args();

        $query = static::query()->with($relations);

        // Set constraints to only get this model
        $query->where($this->primaryKey, $this->getKey());

        // Get the model with relations
        $model = $query->first();

        // Copy loaded relations to this instance
        if ($model) {
            foreach ($relations as $relation) {
                if (isset($model->relations[$relation])) {
                    $this->setRelation($relation, $model->relations[$relation]);
                }
            }
        }

        return $this;
    }

    /**
     * Dynamically set attributes on the model.
     */
    public function __set(string $key, $value): void
    {
        $this->setAttribute($key, $value);
    }


    public function __clone()
    {
        // Deep clone relations
        $this->relations = array_map(function ($relation) {
            if ($relation instanceof Collection) {
                return clone $relation;
            }
            if (is_object($relation)) {
                return clone $relation;
            }
            return $relation;
        }, $this->relations);
    }


    public static function __callStatic(string $method, array $parameters)
    {
        return static::newQuery()->$method(...$parameters);
    }

    public function __call(string $method, array $parameters)
    {
        return static::newQuery()->$method(...$parameters);
    }
}
