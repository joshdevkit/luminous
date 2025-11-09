<?php


namespace Core\Contracts\Routing;

/**
 * Interface ArraySerializable
 *
 * Provides a contract for objects that can be represented as an array.
 *
 * Implementing classes should define how their internal data structure
 * can be transformed into an associative array.
 *
 * @package Core\Contracts\Routing;
 */

interface ArraySerializable
{
     /**
     * Convert the object’s internal data to an associative array.
     *
     * @return array<string, mixed> The array representation of the object.
     */
    public function toArray(): array;
}
