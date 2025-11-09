<?php

namespace Core\Database\Traits;

trait HasAttributes
{
    public function setAttribute(string $key, mixed $value): void
    {
        // Check for mutator first
        if ($this->hasSetMutator($key)) {
            $method = 'set' . str_replace('_', '', ucwords($key, '_')) . 'Attribute';
            $this->$method($value);
            return;
        }

        // Apply casting on set (for password, array, json, etc.)
        if ($this->shouldCastOnSet($key)) {
            $value = $this->castAttribute($key, $value);
        }

        $this->attributes[$key] = $value;
    }

    public function getAttribute(string $key): mixed
    {
        if (!array_key_exists($key, $this->attributes)) {
            // Check for accessor
            if ($this->hasGetMutator($key)) {
                return $this->mutateAttribute($key);
            }
            
            // Check for appended attribute
            if (in_array($key, $this->appends)) {
                return $this->mutateAttribute($key);
            }
            
            return null;
        }

        $value = $this->attributes[$key];

        // Check for accessor
        if ($this->hasGetMutator($key)) {
            return $this->mutateAttribute($key, $value);
        }

        // Apply casting on get (for int, bool, date, etc.)
        if ($this->shouldCastOnGet($key)) {
            return $this->castAttribute($key, $value);
        }

        return $value;
    }

    public function hasAttribute(string $key): bool
    {
        return array_key_exists($key, $this->attributes) 
            || $this->hasGetMutator($key) 
            || in_array($key, $this->appends);
    }

    protected function hasGetMutator(string $key): bool
    {
        $method = 'get' . str_replace('_', '', ucwords($key, '_')) . 'Attribute';
        return method_exists($this, $method);
    }

    protected function hasSetMutator(string $key): bool
    {
        $method = 'set' . str_replace('_', '', ucwords($key, '_')) . 'Attribute';
        return method_exists($this, $method);
    }

    protected function mutateAttribute(string $key, mixed $value = null): mixed
    {
        $method = 'get' . str_replace('_', '', ucwords($key, '_')) . 'Attribute';
        return $this->$method($value);
    }

    public function __get(string $key)
    {
        return $this->getAttribute($key);
    }

    public function __set(string $key, mixed $value)
    {
        $this->setAttribute($key, $value);
    }

    public function __isset(string $key)
    {
        return isset($this->attributes[$key]);
    }
}