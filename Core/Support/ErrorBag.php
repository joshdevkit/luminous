<?php

namespace Core\Support;


class ErrorBag
{
    /**
     * @var array<string, array<int, string>> $errors
     */
    protected array $errors = [];

    public function __construct(array $errors = [])
    {
        $this->errors = $errors;
    }

    /**
     * Add an error message for a given field.
     */
    public function add(string $field, string $message): void
    {
        $this->errors[$field][] = $message;
    }

    /**
     * Return true if there are any errors.
     */
    public function any(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Alias for any() - checks if there are any errors.
     */
    public function hasAny(): bool
    {
        return $this->any();
    }


    /**
     * Check if a specific field has an error.
     */
    public function has(string $field): bool
    {
        return !empty($this->errors[$field]);
    }

    /**
     * Return all error messages as a flat array.
     *
     * @return array<int, string>
     */
    public function all(): array
    {
        return array_merge(...array_values($this->errors ?: [[]]));
    }

    /**
     * Get errors for a specific field.
     *
     * @return array<int, string>
     */
    public function get(string $field): array
    {
        return $this->errors[$field] ?? [];
    }

    /**
     * Get the first error message for a specific field.
     */
    public function first(string $field): ?string
    {
        return $this->errors[$field][0] ?? null;
    }
}
