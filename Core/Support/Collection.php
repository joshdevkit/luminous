<?php

namespace Core\Support;

class Collection implements \Countable, \IteratorAggregate, \ArrayAccess, \JsonSerializable
{
    protected array $items = [];

    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    /**
     * Check if the collection is empty
     */
    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    /**
     * Check if the collection is not empty
     */
    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    /**
     * Get the number of items
     */
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * Get the first item
     */
    public function first(?callable $callback = null, mixed $default = null): mixed
    {
        if (is_null($callback)) {
            return $this->items[0] ?? $default;
        }

        foreach ($this->items as $key => $value) {
            if ($callback($value, $key)) {
                return $value;
            }
        }

        return $default;
    }

    /**
     * Get the last item
     */
    public function last(?callable $callback = null, mixed $default = null): mixed
    {
        if (is_null($callback)) {
            return !empty($this->items) ? end($this->items) : $default;
        }

        return $this->reverse()->first($callback, $default);
    }

    /**
     * Get all items as array
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * Map over the items
     */
    public function map(callable $callback): static
    {
        $keys = array_keys($this->items);
        $items = array_map($callback, $this->items, $keys);

        return new static(array_combine($keys, $items));
    }

    public function where(string $key, mixed $operator, mixed $value = null): static
    {
        $callback = $this->operatorForWhere($key, $operator, $value);
        return $this->filter($callback);
    }


    /**
     * Filter items using callback
     */
    public function filter(?callable $callback = null): static
    {
        if ($callback) {
            return new static(array_filter($this->items, $callback, ARRAY_FILTER_USE_BOTH));
        }

        return new static(array_filter($this->items));
    }

    /**
     * Reject items using callback
     */
    public function reject(callable $callback): static
    {
        return $this->filter(fn($item, $key) => !$callback($item, $key));
    }

    /**
     * Get values of a given key
     */
    public function pluck(string $value, ?string $key = null): static
    {
        $results = [];

        foreach ($this->items as $item) {
            $itemValue = $this->dataGet($item, $value);

            if (is_null($key)) {
                $results[] = $itemValue;
            } else {
                $itemKey = $this->dataGet($item, $key);
                $results[$itemKey] = $itemValue;
            }
        }

        return new static($results);
    }

    /**
     * Select only specific columns from collection items
     * Works with arrays and objects
     * 
     * @param array|string $columns Columns to keep
     * @return static
     * 
     * Examples:
     * $collection->selectOnly(['id', 'name', 'price'])
     * $collection->selectOnly('id', 'name', 'price')
     */
    public function selectOnly(array|string $columns): static
    {
        if (is_string($columns)) {
            $columns = func_get_args();
        }

        return $this->map(function ($item) use ($columns) {
            if (is_array($item)) {
                return array_intersect_key($item, array_flip($columns));
            }

            if (is_object($item)) {
                $filtered = new \stdClass();
                foreach ($columns as $column) {
                    if (isset($item->$column)) {
                        $filtered->$column = $item->$column;
                    }
                }
                return $filtered;
            }

            return $item;
        });
    }


    /**
     * Filter specific columns from nested relationships using Model visibility
     * This keeps Models as Model instances instead of converting them
     * 
     * @param string $relation Relation path (e.g., 'items.product')
     * @param array $columns Columns to keep visible
     * @return static
     * 
     * Examples:
     * $orders->filterColumns('product', ['id', 'name', 'price'])
     * $orders->filterColumns('items.product', ['id', 'name'])
     */
    public function filterColumns(string $relation, array $columns): static
    {
        $parts = explode('.', $relation);

        return $this->map(function ($item) use ($parts, $columns) {
            return $this->applyColumnFilter($item, $parts, $columns);
        });
    }



    /**
     * Recursively apply column filters to nested relationships
     */
    protected function applyColumnFilter(mixed $item, array $parts, array $columns): mixed
    {
        if (empty($parts) || !is_object($item)) {
            return $item;
        }

        $key = array_shift($parts);

        try {
            // Access the relation/property
            $value = $item->$key;

            if (is_null($value)) {
                return $item;
            }

            if (empty($parts)) {
                // We've reached the final destination
                if ($value instanceof \Core\Support\Collection) {
                    // Collection of models - apply setVisible to each
                    $value->map(function ($model) use ($columns) {
                        if (method_exists($model, 'setVisible')) {
                            $model->setVisible($columns);
                        }
                        return $model;
                    });
                } elseif (is_array($value)) {
                    // Array of items
                    array_map(function ($model) use ($columns) {
                        if (is_object($model) && method_exists($model, 'setVisible')) {
                            $model->setVisible($columns);
                        }
                        return $model;
                    }, $value);
                } elseif (is_object($value) && method_exists($value, 'setVisible')) {
                    // Single model
                    $value->setVisible($columns);
                }
            } else {
                // Continue recursion for nested relations
                if ($value instanceof \Core\Support\Collection) {
                    $value->map(fn($subItem) => $this->applyColumnFilter($subItem, $parts, $columns));
                } elseif (is_array($value)) {
                    array_map(fn($subItem) => $this->applyColumnFilter($subItem, $parts, $columns), $value);
                } else {
                    $this->applyColumnFilter($value, $parts, $columns);
                }
            }
        } catch (\Throwable $e) {
            // Relation doesn't exist, return item as-is
        }

        return $item;
    }

    /**
     * Recursively filter columns in nested relationships (legacy method - converts to stdClass)
     * Use filterColumns() instead for better Model support
     */
    protected function filterNestedColumns(mixed $item, array $parts, array $columns): mixed
    {
        if (empty($parts)) {
            return $this->filterItemColumns($item, $columns);
        }

        $key = array_shift($parts);

        // Handle array items
        if (is_array($item)) {
            if (isset($item[$key])) {
                if (is_array($item[$key]) && !empty($parts)) {
                    $item[$key] = array_map(
                        fn($subItem) => $this->filterNestedColumns($subItem, $parts, $columns),
                        $item[$key]
                    );
                } else {
                    $item[$key] = $this->filterNestedColumns($item[$key], $parts, $columns);
                }
            }
            return $item;
        }

        // Handle object items
        if (is_object($item)) {
            $value = null;
            $hasValue = false;

            try {
                $value = $item->$key;
                $hasValue = !is_null($value);
            } catch (\Throwable $e) {
                return $item;
            }

            if (!$hasValue) {
                return $item;
            }

            $filteredValue = null;

            if ($value instanceof \Core\Support\Collection) {
                if (!empty($parts)) {
                    $filteredValue = $value->map(
                        fn($subItem) => $this->filterNestedColumns($subItem, $parts, $columns)
                    );
                } else {
                    $filteredValue = $value->map(
                        fn($subItem) => $this->filterItemColumns($subItem, $columns)
                    );
                }
            } elseif (is_array($value)) {
                if (!empty($parts)) {
                    $filteredValue = array_map(
                        fn($subItem) => $this->filterNestedColumns($subItem, $parts, $columns),
                        $value
                    );
                } else {
                    $filteredValue = array_map(
                        fn($subItem) => $this->filterItemColumns($subItem, $columns),
                        $value
                    );
                }
            } else {
                $filteredValue = $this->filterNestedColumns($value, $parts, $columns);
            }

            $item = clone $item;

            if (method_exists($item, 'setRelation') && method_exists($item, 'relationLoaded')) {
                if ($item->relationLoaded($key)) {
                    $item->setRelation($key, $filteredValue);
                } else {
                    $item->$key = $filteredValue;
                }
            } else {
                $item->$key = $filteredValue;
            }

            return $item;
        }

        return $item;
    }


    /**
     * Filter columns from a single item (legacy method - converts to stdClass)
     */
    protected function filterItemColumns(mixed $item, array $columns): mixed
    {
        if (is_array($item)) {
            return array_intersect_key($item, array_flip($columns));
        }

        if (is_object($item)) {
            $filtered = new \stdClass();

            foreach ($columns as $column) {
                try {
                    $value = $item->$column;
                    $filtered->$column = $value;
                } catch (\Throwable $e) {
                    continue;
                }
            }

            return $filtered;
        }

        return $item;
    }
    /**
     * Exclude specific columns from collection items
     * 
     * @param array|string $columns Columns to exclude
     * @return static
     * 
     * Examples:
     * $collection->except(['password', 'remember_token'])
     * $collection->except('password', 'remember_token')
     */
    public function except(array|string $columns): static
    {
        if (is_string($columns)) {
            $columns = func_get_args();
        }

        return $this->map(function ($item) use ($columns) {
            if (is_array($item)) {
                return array_diff_key($item, array_flip($columns));
            }

            if (is_object($item)) {
                $filtered = clone $item;
                foreach ($columns as $column) {
                    unset($filtered->$column);
                }
                return $filtered;
            }

            return $item;
        });
    }

    /**
     * Get only specific keys from each item
     * Alias for selectOnly for consistency with Laravel
     * 
     * @param array|string $keys Keys to keep
     * @return static
     */
    public function only(array|string $keys): static
    {
        if (is_string($keys)) {
            $keys = func_get_args();
        }

        return $this->selectOnly($keys);
    }

    /**
     * Transform nested relations in the collection
     * 
     * @param string $relation Relation path
     * @param callable $callback Transformation callback
     * @return static
     * 
     * Examples:
     * $orders->transformRelation('items.product', fn($product) => [
     *     'id' => $product->id,
     *     'name' => strtoupper($product->name)
     * ])
     */
    public function transformRelation(string $relation, callable $callback): static
    {
        $parts = explode('.', $relation);

        return $this->map(function ($item) use ($parts, $callback) {
            return $this->transformNestedRelation($item, $parts, $callback);
        });
    }

    /**
     * Recursively transform nested relations
     */
    protected function transformNestedRelation(mixed $item, array $parts, callable $callback): mixed
    {
        if (empty($parts)) {
            return $callback($item);
        }

        $key = array_shift($parts);

        if (is_array($item)) {
            if (isset($item[$key])) {
                if (is_array($item[$key]) && !empty($parts)) {
                    $item[$key] = array_map(
                        fn($subItem) => $this->transformNestedRelation($subItem, $parts, $callback),
                        $item[$key]
                    );
                } else {
                    $item[$key] = $this->transformNestedRelation($item[$key], $parts, $callback);
                }
            }
            return $item;
        }

        if (is_object($item)) {
            if (isset($item->$key)) {
                if (is_array($item->$key) && !empty($parts)) {
                    $item->$key = array_map(
                        fn($subItem) => $this->transformNestedRelation($subItem, $parts, $callback),
                        $item->$key
                    );
                } else {
                    $item->$key = $this->transformNestedRelation($item->$key, $parts, $callback);
                }
            }
            return $item;
        }

        return $item;
    }

    /**
     * Get all of the collection's keys.
     */
    public function keys(): static
    {
        return new static(array_keys($this->items));
    }

    /**
     * Determine if any of the items in the collection pass the given truth test.
     *
     * @param  callable|null  $callback
     * @return bool
     */
    public function some(?callable $callback = null): bool
    {
        if (is_null($callback)) {
            // If no callback given, return true if collection is not empty
            return $this->isNotEmpty();
        }

        foreach ($this->items as $key => $value) {
            if ($callback($value, $key)) {
                return true;
            }
        }

        return false;
    }


    // /**
    //  * Get all items
    //  */
    // public function items(): Collection
    // {
    //     return new Collection($this->items);
    // }
    /**
     * Get an item from an array or object using "dot" notation
     */
    protected function dataGet(mixed $target, string $key): mixed
    {
        if (is_null($key)) {
            return $target;
        }

        foreach (explode('.', $key) as $segment) {
            if (is_array($target)) {
                if (!array_key_exists($segment, $target)) {
                    return null;
                }
                $target = $target[$segment];
            } elseif (is_object($target)) {
                if (!isset($target->{$segment})) {
                    return null;
                }
                $target = $target->{$segment};
            } else {
                return null;
            }
        }

        return $target;
    }

    /**
     * Reduce the collection to a single value
     */
    public function reduce(callable $callback, mixed $initial = null): mixed
    {
        return array_reduce($this->items, $callback, $initial);
    }

    /**
     * Get unique items
     */
    public function unique(?string $key = null): static
    {
        if (is_null($key)) {
            return new static(array_unique($this->items, SORT_REGULAR));
        }

        $exists = [];
        return $this->reject(function ($item) use ($key, &$exists) {
            $id = $this->dataGet($item, $key);
            if (in_array($id, $exists, true)) {
                return true;
            }
            $exists[] = $id;
            return false;
        });
    }

    /**
     * Sort items
     */
    public function sort(?callable $callback = null): static
    {
        $items = $this->items;

        $callback ? uasort($items, $callback) : asort($items);

        return new static($items);
    }

    /**
     * Sort items by key
     */
    public function sortBy(callable|string $callback, int $options = SORT_REGULAR, bool $descending = false): static
    {
        $results = [];

        if (is_string($callback)) {
            $key = $callback;
            $callback = fn($item) => $this->dataGet($item, $key);
        }

        foreach ($this->items as $k => $value) {
            $results[$k] = $callback($value, $k);
        }

        $descending ? arsort($results, $options) : asort($results, $options);

        foreach (array_keys($results) as $key) {
            $results[$key] = $this->items[$key];
        }

        return new static($results);
    }

    /**
     * Sort items in descending order
     */
    public function sortByDesc(callable|string $callback, int $options = SORT_REGULAR): static
    {
        return $this->sortBy($callback, $options, true);
    }

    /**
     * Reverse items order
     */
    public function reverse(): static
    {
        return new static(array_reverse($this->items, true));
    }

    /**
     * Get a slice of items
     */
    public function slice(int $offset, ?int $length = null): static
    {
        return new static(array_slice($this->items, $offset, $length, true));
    }

    /**
     * Take the first n items
     */
    public function take(int $limit): static
    {
        if ($limit < 0) {
            return $this->slice($limit, abs($limit));
        }

        return $this->slice(0, $limit);
    }

    /**
     * Chunk items into arrays
     */
    public function chunk(int $size): static
    {
        $chunks = [];

        foreach (array_chunk($this->items, $size, true) as $chunk) {
            $chunks[] = new static($chunk);
        }

        return new static($chunks);
    }

    /**
     * Check if an item exists using callback
     */
    public function contains(callable|string $key, mixed $operator = null, mixed $value = null): bool
    {
        if (func_num_args() === 1 && is_callable($key)) {
            foreach ($this->items as $item) {
                if ($key($item)) {
                    return true;
                }
            }
            return false;
        }

        if (func_num_args() === 1) {
            return in_array($key, $this->items, true);
        }

        return $this->contains($this->operatorForWhere(...func_get_args()));
    }

    /**
     * Get the sum of values
     */
    public function sum(callable|string|null $callback = null): mixed
    {
        if (is_null($callback)) {
            return array_sum($this->items);
        }

        $callback = is_string($callback)
            ? fn($item) => $this->dataGet($item, $callback)
            : $callback;

        return $this->reduce(fn($result, $item) => $result + $callback($item), 0);
    }

    /**
     * Get the average value
     */
    public function avg(callable|string|null $callback = null): mixed
    {
        $count = $this->count();

        if ($count === 0) {
            return null;
        }

        return $this->sum($callback) / $count;
    }

    /**
     * Get the max value
     */
    public function max(callable|string|null $callback = null): mixed
    {
        if (is_null($callback)) {
            return max($this->items);
        }

        $callback = is_string($callback)
            ? fn($item) => $this->dataGet($item, $callback)
            : $callback;

        return $this->reduce(function ($result, $item) use ($callback) {
            $value = $callback($item);
            return is_null($result) || $value > $result ? $value : $result;
        });
    }

    /**
     * Get the min value
     */
    public function min(callable|string|null $callback = null): mixed
    {
        if (is_null($callback)) {
            return min($this->items);
        }

        $callback = is_string($callback)
            ? fn($item) => $this->dataGet($item, $callback)
            : $callback;

        return $this->reduce(function ($result, $item) use ($callback) {
            $value = $callback($item);
            return is_null($result) || $value < $result ? $value : $result;
        });
    }

    /**
     * Group items by key
     */
    public function groupBy(callable|string $groupBy): static
    {
        $groupBy = is_string($groupBy)
            ? fn($item) => $this->dataGet($item, $groupBy)
            : $groupBy;

        $results = [];

        foreach ($this->items as $key => $value) {
            $groupKey = $groupBy($value, $key);

            if (!array_key_exists($groupKey, $results)) {
                $results[$groupKey] = new static();
            }

            $results[$groupKey]->items[] = $value;
        }

        return new static($results);
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return array_map(function ($item) {
            return $item instanceof \JsonSerializable ? $item->jsonSerialize() : $item;
        }, $this->items);
    }

    /**
     * Convert to JSON
     */
    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }

    /**
     * Json serialize
     */
    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    /**
     * Get iterator
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->items);
    }

    /**
     * Check if offset exists
     */
    public function offsetExists($offset): bool
    {
        return isset($this->items[$offset]);
    }

    /**
     * Get offset
     */
    public function offsetGet($offset): mixed
    {
        return $this->items[$offset] ?? null;
    }

    /**
     * Set offset
     */
    public function offsetSet($offset, $value): void
    {
        if (is_null($offset)) {
            $this->items[] = $value;
        } else {
            $this->items[$offset] = $value;
        }
    }

    /**
     * Unset offset
     */
    public function offsetUnset($offset): void
    {
        unset($this->items[$offset]);
    }

    /**
     * Get items as string
     */
    public function __toString(): string
    {
        return $this->toJson();
    }

    /**
     * Create operator callback for where
     */
    protected function operatorForWhere(string $key, mixed $operator, mixed $value = null): callable
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        return function ($item) use ($key, $operator, $value) {
            $retrieved = $this->dataGet($item, $key);

            return match ($operator) {
                '=' => $retrieved == $value,
                '===' => $retrieved === $value,
                '!=' => $retrieved != $value,
                '!==' => $retrieved !== $value,
                '>' => $retrieved > $value,
                '>=' => $retrieved >= $value,
                '<' => $retrieved < $value,
                '<=' => $retrieved <= $value,
                default => false,
            };
        };
    }
}
