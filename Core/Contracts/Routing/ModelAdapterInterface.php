<?php

declare(strict_types=1);

namespace Core\Contracts\Routing;

/**
 * Interface ModelAdapterInterface
 *
 * Defines the contract for converting form data into model instances.
 * 
 * This abstraction allows flexible data mapping between form DTOs and models,
 * supporting multiple storage backends (e.g., ORM models, API DTOs, etc.).
 *
 * @package Core\Contracts\Routing;
 */

interface ModelAdapterInterface
{
    /**
     * Populate an existing model instance with the given data.
     *
     * @param object $model The model instance to fill.
     * @param array<string, mixed> $data The key-value pairs to assign.
     *
     * @return object The filled model instance.
     */
    public function fill(object $model, array $data): object;

    /**
     * Create a new model instance using the given class name and data.
     *
     * @param string $class The fully qualified model class name.
     * @param array<string, mixed> $data The key-value pairs to assign.
     *
     * @return object The newly created and populated model instance.
     */
    public function create(string $class, array $data): object;
}
