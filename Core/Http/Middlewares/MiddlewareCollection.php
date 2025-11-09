<?php

namespace Core\Http\Middlewares;

use InvalidArgumentException;

class MiddlewareCollection implements \IteratorAggregate, \Countable, \ArrayAccess
{
    /**
     * @var string[]
     */
    protected array $middleware = [];

    /**
     * @var array<string, int>
     */
    protected array $priorities = [];

    /**
     * @param string[] $middleware
     */
    public function __construct(array $middleware = [])
    {
        foreach ($middleware as $item) {
            $this->add($item);
        }

        // dd($middleware);
    }

    /**
     * Add a middleware class to the collection.
     *
     * @param string $middleware The fully qualified middleware class name
     * @param int $priority Optional priority (higher runs first)
     * @return $this
     * @throws InvalidArgumentException
     */
    public function add(string $middleware, int $priority = 0): self
    {
        if (empty($middleware)) {
            throw new InvalidArgumentException('Middleware class name cannot be empty');
        }

        if (!$this->contains($middleware)) {
            $this->middleware[] = $middleware;
            $this->priorities[$middleware] = $priority;
            
            if ($priority !== 0) {
                $this->sort();
            }
        }

        return $this;
    }

    /**
     * Add a middleware to the beginning of the collection.
     *
     * @param string $middleware
     * @return $this
     */
    public function prepend(string $middleware): self
    {
        if (!$this->contains($middleware)) {
            array_unshift($this->middleware, $middleware);
            $this->priorities[$middleware] = 0;
        }

        return $this;
    }

    /**
     * Remove a middleware from the collection.
     *
     * @param string $middleware
     * @return $this
     */
    public function remove(string $middleware): self
    {
        $this->middleware = array_values(
            array_filter($this->middleware, fn($item) => $item !== $middleware)
        );
        
        unset($this->priorities[$middleware]);

        return $this;
    }

    /**
     * Check if the middleware exists in the collection.
     *
     * @param string $middleware
     * @return bool
     */
    public function contains(string $middleware): bool
    {
        return in_array($middleware, $this->middleware, true);
    }

    /**
     * Merge another collection or array into this one.
     *
     * @param array|self $middleware
     * @return $this
     */
    public function merge(array|self $middleware): self
    {
        $items = $middleware instanceof self ? $middleware->all() : $middleware;
        
        foreach ($items as $item) {
            $priority = $middleware instanceof self 
                ? $middleware->getPriority($item) 
                : 0;
            
            $this->add($item, $priority);
        }

        return $this;
    }

    /**
     * Replace a middleware with another one.
     *
     * @param string $old
     * @param string $new
     * @return $this
     */
    public function replace(string $old, string $new): self
    {
        $key = array_search($old, $this->middleware, true);
        
        if ($key !== false) {
            $this->middleware[$key] = $new;
            $this->priorities[$new] = $this->priorities[$old] ?? 0;
            unset($this->priorities[$old]);
        }

        return $this;
    }

    /**
     * Insert a middleware before another middleware.
     *
     * @param string $before
     * @param string $middleware
     * @return $this
     */
    public function insertBefore(string $before, string $middleware): self
    {
        $key = array_search($before, $this->middleware, true);
        
        if ($key !== false && !$this->contains($middleware)) {
            array_splice($this->middleware, $key, 0, [$middleware]);
            $this->priorities[$middleware] = 0;
        }

        return $this;
    }

    /**
     * Insert a middleware after another middleware.
     *
     * @param string $after
     * @param string $middleware
     * @return $this
     */
    public function insertAfter(string $after, string $middleware): self
    {
        $key = array_search($after, $this->middleware, true);
        
        if ($key !== false && !$this->contains($middleware)) {
            array_splice($this->middleware, $key + 1, 0, [$middleware]);
            $this->priorities[$middleware] = 0;
        }

        return $this;
    }

    /**
     * Get the priority of a middleware.
     *
     * @param string $middleware
     * @return int
     */
    public function getPriority(string $middleware): int
    {
        return $this->priorities[$middleware] ?? 0;
    }

    /**
     * Sort middleware by priority (higher priority first).
     *
     * @return $this
     */
    protected function sort(): self
    {
        usort($this->middleware, function ($a, $b) {
            return ($this->priorities[$b] ?? 0) <=> ($this->priorities[$a] ?? 0);
        });

        return $this;
    }

    /**
     * Clear all middleware from the collection.
     *
     * @return $this
     */
    public function clear(): self
    {
        $this->middleware = [];
        $this->priorities = [];

        return $this;
    }

    /**
     * Check if the collection is empty.
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return empty($this->middleware);
    }

    /**
     * Get the first middleware in the collection.
     *
     * @return string|null
     */
    public function first(): ?string
    {
        return $this->middleware[0] ?? null;
    }

    /**
     * Get the last middleware in the collection.
     *
     * @return string|null
     */
    public function last(): ?string
    {
        return $this->middleware[count($this->middleware) - 1] ?? null;
    }

    /**
     * Filter middleware based on a callback.
     *
     * @param callable $callback
     * @return static
     */
    public function filter(callable $callback): static
    {
        $filtered = new static();
        
        foreach ($this->middleware as $item) {
            if ($callback($item)) {
                $filtered->add($item, $this->getPriority($item));
            }
        }

        return $filtered;
    }

    /**
     * Return all middleware.
     *
     * @return string[]
     */
    public function all(): array
    {
        return $this->middleware;
    }

    /**
     * Convert the collection to an array.
     *
     * @return string[]
     */
    public function toArray(): array
    {
        return $this->middleware;
    }

    /**
     * Get an iterator for the middleware.
     *
     * @return \ArrayIterator
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->middleware);
    }

    /**
     * Count the middleware in the collection.
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->middleware);
    }

    /**
     * Check if an offset exists.
     *
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->middleware[$offset]);
    }

    /**
     * Get a middleware at a specific offset.
     *
     * @param mixed $offset
     * @return string|null
     */
    public function offsetGet(mixed $offset): ?string
    {
        return $this->middleware[$offset] ?? null;
    }

    /**
     * Set a middleware at a specific offset.
     *
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (is_null($offset)) {
            $this->add($value);
        } else {
            $this->middleware[$offset] = $value;
            $this->priorities[$value] = 0;
        }
    }

    /**
     * Unset a middleware at a specific offset.
     *
     * @param mixed $offset
     */
    public function offsetUnset(mixed $offset): void
    {
        if (isset($this->middleware[$offset])) {
            $middleware = $this->middleware[$offset];
            unset($this->priorities[$middleware]);
            unset($this->middleware[$offset]);
            $this->middleware = array_values($this->middleware);
        }
    }

    /**
     * Create a new collection from an array.
     *
     * @param string[] $middleware
     * @return static
     */
    public static function make(array $middleware = []): static
    {
        return new static($middleware);
    }
}