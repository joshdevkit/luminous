<?php

use Core\Support\Collection;

if (!function_exists('collect')) {
    /**
     * Create a collection from the given value
     */
    function collect(mixed $value = []): Collection
    {
        return new Collection(is_array($value) ? $value : [$value]);
    }
}

if (!function_exists('value')) {
    /**
     * Return the default value of the given value
     */
    function value(mixed $value, ...$args): mixed
    {
        return $value instanceof Closure ? $value(...$args) : $value;
    }
}

if (!function_exists('data_get')) {
    /**
     * Get an item from an array or object using "dot" notation
     */
    function data_get(mixed $target, string|array|int|null $key, mixed $default = null): mixed
    {
        if (is_null($key)) {
            return $target;
        }

        $key = is_array($key) ? $key : explode('.', $key);

        foreach ($key as $segment) {
            if (is_array($target)) {
                if (!array_key_exists($segment, $target)) {
                    return value($default);
                }
                $target = $target[$segment];
            } elseif (is_object($target)) {
                if (!isset($target->{$segment})) {
                    return value($default);
                }
                $target = $target->{$segment};
            } else {
                return value($default);
            }
        }

        return $target;
    }
}

if (!function_exists('array_wrap')) {
    /**
     * Wrap the given value in an array if it's not already an array
     */
    function array_wrap(mixed $value): array
    {
        if (is_null($value)) {
            return [];
        }

        return is_array($value) ? $value : [$value];
    }
}

if (!function_exists('head')) {
    /**
     * Get the first element of an array
     */
    function head(array $array): mixed
    {
        return reset($array);
    }
}

if (!function_exists('last')) {
    /**
     * Get the last element of an array
     */
    function last(array $array): mixed
    {
        return end($array);
    }
}

if (!function_exists('tap')) {
    /**
     * Call the given callback with the given value then return the value
     */
    function tap(mixed $value, ?callable $callback = null): mixed
    {
        if (is_null($callback)) {
            return $value;
        }

        $callback($value);

        return $value;
    }
}

if (!function_exists('with')) {
    /**
     * Return the given value, optionally passed through the given callback
     */
    function with(mixed $value, ?callable $callback = null): mixed
    {
        return is_null($callback) ? $value : $callback($value);
    }
}

if (!function_exists('retry')) {
    /**
     * Retry an operation a given number of times
     */
    function retry(int $times, callable $callback, int $sleep = 0)
    {
        $attempts = 0;

        beginning:
        $attempts++;

        try {
            return $callback($attempts);
        } catch (Exception $e) {
            if ($attempts >= $times) {
                throw $e;
            }

            if ($sleep) {
                usleep($sleep * 1000);
            }

            goto beginning;
        }
    }
}

if (!function_exists('blank')) {
    /**
     * Determine if the given value is "blank"
     */
    function blank(mixed $value): bool
    {
        if (is_null($value)) {
            return true;
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        if (is_numeric($value) || is_bool($value)) {
            return false;
        }

        if ($value instanceof Countable) {
            return count($value) === 0;
        }

        return empty($value);
    }
}

if (!function_exists('filled')) {
    /**
     * Determine if the given value is not "blank"
     */
    function filled(mixed $value): bool
    {
        return !blank($value);
    }
}

if (!function_exists('optional')) {
    /**
     * Provide access to optional objects
     */
    function optional(mixed $value = null, ?callable $callback = null): mixed
    {
        if (is_null($callback)) {
            return $value === null ? new class {
                public function __get($key) { return null; }
                public function __call($method, $parameters) { return null; }
            } : $value;
        }

        if ($value !== null) {
            return $callback($value);
        }

        return null;
    }
}

if (!function_exists('transform')) {
    /**
     * Transform the given value if it is present
     */
    function transform(mixed $value, callable $callback, mixed $default = null): mixed
    {
        if (filled($value)) {
            return $callback($value);
        }

        if (is_callable($default)) {
            return $default($value);
        }

        return $default;
    }
}