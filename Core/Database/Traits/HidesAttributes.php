<?php

namespace Core\Database\Traits;

trait HidesAttributes
{
    public function makeVisible(string|array $attributes): self
    {
        $attributes = is_array($attributes) ? $attributes : func_get_args();
        $this->hidden = array_diff($this->hidden, $attributes);
        
        if (count($this->visible) > 0) {
            $this->visible = array_merge($this->visible, $attributes);
        }

        return $this;
    }

    public function makeHidden(string|array $attributes): self
    {
        $this->hidden = array_merge(
            $this->hidden,
            is_array($attributes) ? $attributes : func_get_args()
        );

        return $this;
    }

    public function setVisible(array $visible): self
    {
        $this->visible = $visible;
        return $this;
    }

    public function setHidden(array $hidden): self
    {
        $this->hidden = $hidden;
        return $this;
    }

    public function setAppends(array $appends): self
    {
        $this->appends = $appends;
        return $this;
    }

    protected function getArrayableAttributes(): array
    {
        $attributes = $this->attributes;

        // Add appended attributes
        foreach ($this->appends as $key) {
            $attributes[$key] = $this->getAttribute($key);
        }

        // Apply hidden/visible
        if (count($this->visible) > 0) {
            $attributes = array_intersect_key($attributes, array_flip($this->visible));
        }

        if (count($this->hidden) > 0) {
            $attributes = array_diff_key($attributes, array_flip($this->hidden));
        }

        return $attributes;
    }
}