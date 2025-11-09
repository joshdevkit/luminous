<?php

namespace Core\Database\Schema;

use Core\Database\Capsule;

/**
 * Base Migration Class
 * Simple and intuitive - just define up() and down() methods
 */
abstract class Migration
{
    protected Schema $schema;

    public function __construct()
    {
        $this->schema = new Schema(Capsule::connection());
    }

    /**
     * Run the migration
     */
    abstract public function up(): void;

    /**
     * Reverse the migration
     */
    abstract public function down(): void;

    /**
     * Get migration name from class name
     */
    public function getName(): string
    {
        return static::class;
    }
}