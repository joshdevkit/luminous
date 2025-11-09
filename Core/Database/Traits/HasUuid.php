<?php

namespace Core\Database\Traits;

use Ramsey\Uuid\Uuid;

trait HasUuid
{
    /**
     * Boot the HasUuid trait for a model.
     */
    protected static function bootHasUuid(): void
    {
        static::creating(function ($model) {
            // Only generate UUID if the primary key is not already set
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = static::generateUuid();
            }
        });
    }

    /**
     * Generate a new UUID (version 4 by default)
     */
    public static function generateUuid(): string
    {
        return Uuid::uuid4()->toString();
    }

    /**
     * Get the value indicating whether the IDs are incrementing.
     */
    public function getIncrementing(): bool
    {
        return false;
    }

    /**
     * Get the auto-incrementing key type.
     */
    public function getKeyType(): string
    {
        return 'string';
    }
}