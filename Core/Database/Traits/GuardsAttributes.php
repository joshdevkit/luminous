<?php

namespace Core\Database\Traits;

trait GuardsAttributes
{
    /**
     * Fill the model with an array of attributes.
     */
    public function fill(array $attributes): self
    {
        foreach ($attributes as $key => $value) {
            // Allow if unguarded globally
            if (static::isUnguarded()) {
                $this->setAttribute($key, $value);
                continue;
            }

            // Allow if fillable
            if ($this->isFillable($key)) {
                $this->setAttribute($key, $value);
                continue;
            }

            // Allow primary key (always fillable for database operations)
            if ($key === $this->getKeyName()) {
                $this->setAttribute($key, $value);
                continue;
            }

            // Allow timestamp columns (auto-managed)
            if ($this->timestamps && ($key === static::CREATED_AT || $key === static::UPDATED_AT)) {
                $this->setAttribute($key, $value);
                continue;
            }

            // Allow date columns (auto-managed)
            if (!empty($this->dates) && in_array($key, $this->dates)) {
                $this->setAttribute($key, $value);
                continue;
            }

            // Skip non-fillable attributes
            continue;
        }

        return $this;
    }

    /**
     * Get the fillable attributes for the model.
     */
    protected function getFillableAttributes(): array
    {
        if (!empty($this->entities)) {
            return $this->entities;
        }

        return [];
    }

    /**
     * Determine if the model is totally guarded.
     */
    public function totallyGuarded(): bool
    {
        $entities = $this->getFillableAttributes();
        return empty($entities) && $this->guarded === ['*'];
    }

    /**
     * Determine if the given attribute may be mass assigned.
     */
    public function isFillable(string $key): bool
    {
        $entities = $this->getFillableAttributes();

        // If using $entities, check if key is in it
        if (!empty($entities)) {
            return in_array($key, $entities);
        }

        // If $guarded is ['*'], nothing is fillable
        if ($this->guarded === ['*']) {
            return false;
        }

        // Otherwise, fillable if not in $guarded
        return !in_array($key, $this->guarded);
    }

    /**
     * Determine if the given key is guarded.
     */
    public function isGuarded(string $key): bool
    {
        return !$this->isFillable($key);
    }

    /**
     * Fill the model with an array of attributes. Force mass assignment.
     */
    public function forceFill(array $attributes): self
    {
        return static::unguarded(function () use ($attributes) {
            return $this->fill($attributes);
        });
    }

    /**
     * Run the given callable while being unguarded.
     */
    public static function unguarded(callable $callback): mixed
    {
        $previous = static::$unguarded ?? false;
        static::$unguarded = true;

        try {
            return $callback();
        } finally {
            static::$unguarded = $previous;
        }
    }

    /**
     * Determine if the current state is "unguarded".
     */
    public static function isUnguarded(): bool
    {
        return static::$unguarded ?? false;
    }

    /**
     * Disable all mass assignment restrictions.
     */
    public static function unguard(bool $state = true): void
    {
        static::$unguarded = $state;
    }

    /**
     * Enable the mass assignment restrictions.
     */
    public static function reguard(): void
    {
        static::$unguarded = false;
    }

    /**
     * Get the fillable attributes of a given array.
     */
    protected function fillableFromArray(array $attributes): array
    {
        $entities = $this->getFillableAttributes();
        
        if (!empty($entities)) {
            return array_intersect_key($attributes, array_flip($entities));
        }

        if ($this->guarded === ['*']) {
            return [];
        }

        return array_diff_key($attributes, array_flip($this->guarded));
    }

    /**
     * Get the fillable attributes for the model.
     */
    public function getFillable(): array
    {
        return $this->entities ?? [];
    }

    /**
     * Get the guarded attributes for the model.
     */
    public function getGuarded(): array
    {
        return $this->guarded;
    }

    /**
     * Set the fillable attributes for the model.
     */
    public function fillable(array $entities): self
    {
        $this->entities = $entities;
        return $this;
    }

    /**
     * Set the guarded attributes for the model.
     */
    public function guard(array $guarded): self
    {
        $this->guarded = $guarded;
        return $this;
    }

    /**
     * Merge new fillable attributes with existing fillable attributes on the model.
     */
    public function mergeFillable(array $entities): self
    {
        $this->entities = array_merge($this->entities ?? [], $entities);
        return $this;
    }

    /**
     * Merge new guarded attributes with existing guarded attributes on the model.
     */
    public function mergeGuarded(array $guarded): self
    {
        $this->guarded = array_merge($this->guarded, $guarded);
        return $this;
    }
}