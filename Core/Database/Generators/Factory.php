<?php

namespace Core\Database\Generators;

use Faker\Factory as FakerFactory;
use Faker\Generator;

abstract class Factory
{
    protected Generator $faker;
    protected string $model;
    protected int $count = 1;
    protected array $states = [];

    public function __construct()
    {
        $this->faker = FakerFactory::create();
        $this->model = $this->resolveModelClass();
    }

    /**
     * Define the model's default state.
     */
    abstract public function definition(): array;

    /**
     * Resolve the model class from the PHPDoc annotation.
     */
    protected function resolveModelClass(): string
    {
        $reflectionClass = new \ReflectionClass($this);
        $docComment = $reflectionClass->getDocComment();

        if ($docComment && preg_match('/@extends\s+Factory<\\\\?(.+?)>/', $docComment, $matches)) {
            $modelClass = $matches[1];
            
            // Handle both full and relative class names
            if (!str_starts_with($modelClass, '\\')) {
                $modelClass = '\\' . $modelClass;
            }
            
            return $modelClass;
        }

        // Fallback to inferring from factory class name
        $factoryClass = class_basename($this);
        $modelName = str_replace('Factory', '', $factoryClass);
        
        // Try common model namespaces
        $possibleClasses = [
            "App\\Entities\\{$modelName}",
        ];

        foreach ($possibleClasses as $class) {
            if (class_exists($class)) {
                return $class;
            }
        }

        throw new \RuntimeException(
            "Unable to resolve model class for factory [" . static::class . "]. " .
            "Add @extends Factory<\\App\\Entities\\{$modelName}> to the class docblock."
        );
    }

    /**
     * Get the model class name.
     */
    public function model(): string
    {
        return $this->model;
    }

    /**
     * Create a new factory instance for the given model.
     */
    public static function new(): static
    {
        return new static();
    }

    /**
     * Set the number of models to generate.
     */
    public function count(int $count): static
    {
        $this->count = $count;
        return $this;
    }

    /**
     * Apply a state transformation.
     */
    public function state(array|callable $state): static
    {
        $this->states[] = $state;
        return $this;
    }

    /**
     * Create model instances.
     */
    public function create(array $attributes = []): mixed
    {
        $results = $this->make($attributes);

        if (is_array($results)) {
            foreach ($results as $result) {
                $result->save();
            }
        } else {
            $results->save();
        }

        return $results;
    }

    /**
     * Make model instances without persisting.
     */
    public function make(array $attributes = []): mixed
    {
        if ($this->count === 1) {
            return $this->makeInstance($attributes);
        }

        $instances = [];
        for ($i = 0; $i < $this->count; $i++) {
            $instances[] = $this->makeInstance($attributes);
        }

        return $instances;
    }

    /**
     * Create a single model instance.
     */
    protected function makeInstance(array $attributes = []): mixed
    {
        $modelClass = $this->model();
        
        // Get base definition
        $definition = $this->definition();

        // Apply states
        foreach ($this->states as $state) {
            if (is_callable($state)) {
                $definition = array_merge($definition, $state($this->faker));
            } else {
                $definition = array_merge($definition, $state);
            }
        }

        // Merge with provided attributes (these override everything)
        $definition = array_merge($definition, $attributes);

        return new $modelClass($definition);
    }

    /**
     * Create raw attributes without instantiating model.
     */
    public function raw(array $attributes = []): array
    {
        $definition = $this->definition();

        foreach ($this->states as $state) {
            if (is_callable($state)) {
                $definition = array_merge($definition, $state($this->faker));
            } else {
                $definition = array_merge($definition, $state);
            }
        }

        return array_merge($definition, $attributes);
    }

    /**
     * Magic method to allow calling static methods as instance methods.
     */
    public function __call(string $method, array $parameters)
    {
        // Check if it's a state method (starts with lowercase or is a common state name)
        if (method_exists($this, $method)) {
            return $this->$method(...$parameters);
        }

        // Otherwise, treat it as a state
        return $this->state([$method => $parameters[0] ?? true]);
    }
}