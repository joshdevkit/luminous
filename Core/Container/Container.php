<?php

namespace Core\Container;

use Core\Contracts\Container\ContainerInterface;
use ReflectionClass;
use ReflectionException;
use Closure;
use Core\Exceptions\ContainerException;
use Core\Exceptions\NotFoundException;
use ReflectionNamedType;
use ReflectionUnionType;

class Container implements ContainerInterface
{
    protected array $bindings = [];
    protected array $instances = [];
    protected array $resolved = [];
    protected array $aliases = [];
    protected array $abstractAliases = [];
    protected array $contextualBindings = [];
    protected array $scopedInstances = [];
    protected array $reflectionCache = [];
    protected array $buildStack = [];
    protected array $resolutionStack = [];
    protected array $tags = [];
    protected array $deferredServices = [];
    protected array $loadedProviders = [];
    protected array $extenders = [];
    protected array $methodBindings = [];
    protected ?string $currentScope = null;
    protected bool $compiled = false;
    protected array $compiledData = [];

    public function bind(string $abstract, mixed $concrete = null, bool $shared = false): void
    {
        $concrete = $concrete ?? $abstract;
        
        $this->bindings[$abstract] = [
            'concrete' => $concrete,
            'shared' => $shared
        ];

        unset($this->resolved[$abstract]);
        unset($this->reflectionCache[$abstract]);
        unset($this->instances[$abstract]);
    }

    public function singleton(string $abstract, mixed $concrete = null): void
    {
        $this->bind($abstract, $concrete, true);
    }

    public function instance(string $abstract, mixed $instance): void
    {
        // Remove abstract from deferred services if it was deferred
        unset($this->deferredServices[$abstract]);

        $this->instances[$abstract] = $instance;

        // If we have extenders, apply them
        if (isset($this->extenders[$abstract])) {
            foreach ($this->extenders[$abstract] as $extender) {
                $instance = $extender($instance, $this);
            }
            $this->instances[$abstract] = $instance;
        }
    }

    public function alias(string $abstract, string $alias): void
    {
        if ($alias === $abstract) {
            throw new ContainerException("[{$abstract}] is aliased to itself.");
        }

        if ($this->hasCircularAlias($abstract, $alias)) {
            throw new ContainerException("Circular alias detected: {$alias} -> {$abstract}");
        }

        $this->aliases[$alias] = $abstract;
        $this->abstractAliases[$abstract][] = $alias;
    }

    protected function hasCircularAlias(string $abstract, string $alias): bool
    {
        $checked = [];
        $current = $abstract;

        while (isset($this->aliases[$current])) {
            if ($current === $alias) {
                return true;
            }

            if (isset($checked[$current])) {
                return false;
            }

            $checked[$current] = true;
            $current = $this->aliases[$current];
        }

        return false;
    }

    public function isAlias(string $name): bool
    {
        return isset($this->aliases[$name]);
    }

    public function getAlias(string $abstract): string
    {
        if (!isset($this->aliases[$abstract])) {
            return $abstract;
        }

        return $this->getAlias($this->aliases[$abstract]);
    }

    /**
     * Define a contextual binding
     */
    public function when(string $concrete): ContextualBindingBuilder
    {
        return new ContextualBindingBuilder($this, $concrete);
    }

    public function addContextualBinding(string $concrete, string $abstract, mixed $implementation): void
    {
        $this->contextualBindings[$concrete][$abstract] = $implementation;
    }

    protected function getContextualConcrete(string $abstract): mixed
    {
        if (empty($this->buildStack)) {
            return null;
        }

        $parent = end($this->buildStack);

        if (isset($this->contextualBindings[$parent][$abstract])) {
            return $this->contextualBindings[$parent][$abstract];
        }

        return null;
    }

    /**
     * Begin a new scope
     */
    public function beginScope(string $scopeName): void
    {
        $this->currentScope = $scopeName;
        $this->scopedInstances[$scopeName] = [];
    }

    /**
     * End the current scope
     */
    public function endScope(): void
    {
        if ($this->currentScope !== null) {
            unset($this->scopedInstances[$this->currentScope]);
            $this->currentScope = null;
        }
    }

    /**
     * Bind a scoped service
     */
    public function scoped(string $abstract, mixed $concrete = null): void
    {
        $concrete = $concrete ?? $abstract;

        $this->bindings[$abstract] = [
            'concrete' => $concrete,
            'shared' => false,
            'scoped' => true
        ];
    }

    /**
     * Bind a primitive/scalar value
     */
    public function bindValue(string $name, mixed $value): void
    {
        $this->instances[$name] = $value;
    }

    /**
     * Tag services for batch resolution
     */
    public function tag(string|array $abstracts, string|array $tags): void
    {
        $tags = is_array($tags) ? $tags : [$tags];

        foreach ($tags as $tag) {
            if (!isset($this->tags[$tag])) {
                $this->tags[$tag] = [];
            }

            foreach ((array) $abstracts as $abstract) {
                $this->tags[$tag][] = $abstract;
            }
        }
    }

    /**
     * Resolve all services with a given tag
     */
    public function tagged(string $tag): array
    {
        if (!isset($this->tags[$tag])) {
            return [];
        }

        return array_map(fn($abstract) => $this->make($abstract), $this->tags[$tag]);
    }

    /**
     * Register a deferred service provider
     */
    public function deferredProvider(string $abstract, string $providerClass): void
    {
        $this->deferredServices[$abstract] = $providerClass;
    }

    /**
     * Load a deferred provider if needed
     */
    protected function loadDeferredProvider(string $abstract): void
    {
        if (!isset($this->deferredServices[$abstract])) {
            return;
        }

        $provider = $this->deferredServices[$abstract];

        if (isset($this->loadedProviders[$provider])) {
            return;
        }

        $providerInstance = new $provider($this);
        $providerInstance->register();

        $this->loadedProviders[$provider] = true;
        unset($this->deferredServices[$abstract]);
    }

    /**
     * Extend a service after it's resolved
     */
    public function extend(string $abstract, Closure $closure): void
    {
        $abstract = $this->getAlias($abstract);

        if (isset($this->instances[$abstract])) {
            $this->instances[$abstract] = $closure($this->instances[$abstract], $this);
        } else {
            $this->extenders[$abstract][] = $closure;
        }
    }

    /**
     * Bind a method call
     */
    public function bindMethod(string $method, Closure $callback): void
    {
        $this->methodBindings[$method] = $callback;
    }

    /**
     * Call a method with dependency injection
     */
    public function call(callable|string $callback, array $parameters = []): mixed
    {
        if (is_string($callback) && isset($this->methodBindings[$callback])) {
            return $this->methodBindings[$callback]($this, $parameters);
        }

        return $this->callBoundMethod($callback, $parameters);
    }

    protected function callBoundMethod(callable|string $callback, array $parameters): mixed
    {
        if (is_string($callback) && str_contains($callback, '@')) {
            [$class, $method] = explode('@', $callback, 2);
            $callback = [$this->make($class), $method];
        }

        if (is_array($callback)) {
            $reflector = new \ReflectionMethod($callback[0], $callback[1]);
        } else {
            $reflector = new \ReflectionFunction($callback);
        }

        $dependencies = $this->resolveDependencies($reflector->getParameters(), $parameters);

        return $reflector->invokeArgs(
            is_array($callback) ? $callback[0] : null,
            $dependencies
        );
    }

    /**
     * Create a lazy proxy for an abstract
     */
    public function lazy(string $abstract): LazyProxy
    {
        return new LazyProxy($this, $abstract);
    }

    /**
     * Forget an instance (memory management)
     */
    public function forgetInstance(string $abstract): void
    {
        $abstract = $this->getAlias($abstract);

        unset($this->instances[$abstract]);
        unset($this->resolved[$abstract]);

        if ($this->currentScope !== null) {
            unset($this->scopedInstances[$this->currentScope][$abstract]);
        }
    }

    /**
     * Forget all scoped instances (cleanup)
     */
    public function forgetScopedInstances(): void
    {
        foreach ($this->scopedInstances as $scope => $instances) {
            $this->scopedInstances[$scope] = [];
        }
    }

    public function get(string $id): mixed
    {
        try {
            return $this->make($id);
        } catch (\Exception $e) {
            if ($this->has($id)) {
                throw new ContainerException(
                    "Error resolving '{$id}': " . $e->getMessage() .
                        "\nResolution stack: " . $this->formatResolutionStack(),
                    0,
                    $e
                );
            }
            throw new NotFoundException("No entry found for '{$id}'");
        }
    }

    public function has(string $id): bool
    {
        return $this->bound($id) || isset($this->instances[$id]);
    }

    public function bound(string $abstract): bool
    {
        return isset($this->bindings[$abstract]) ||
            isset($this->instances[$abstract]) ||
            isset($this->deferredServices[$abstract]) ||
            $this->isAlias($abstract);
    }

    public function make(string $abstract, array $parameters = []): mixed
    {
        $abstract = $this->getAlias($abstract);

        // Check if we should load a deferred provider
        $this->loadDeferredProvider($abstract);

        $this->resolutionStack[] = $abstract;

        try {
            if ($this->isCircularDependency($abstract)) {
                throw new ContainerException(
                    "Circular dependency detected: " . implode(' -> ', $this->buildStack) . " -> {$abstract}"
                );
            }

            if (isset($this->instances[$abstract])) {
                array_pop($this->resolutionStack);
                return $this->instances[$abstract];
            }

            if (
                $this->currentScope !== null &&
                isset($this->scopedInstances[$this->currentScope][$abstract])
            ) {
                array_pop($this->resolutionStack);
                return $this->scopedInstances[$this->currentScope][$abstract];
            }

            $concrete = $this->getContextualConcrete($abstract) ?? $this->getConcrete($abstract);

            if (
                isset($this->bindings[$abstract]['shared']) &&
                $this->bindings[$abstract]['shared'] &&
                isset($this->resolved[$abstract])
            ) {
                array_pop($this->resolutionStack);
                return $this->resolved[$abstract];
            }

            $this->buildStack[] = $abstract;
            $object = $this->build($concrete, $parameters);
            array_pop($this->buildStack);

            // Apply extenders
            if (isset($this->extenders[$abstract])) {
                foreach ($this->extenders[$abstract] as $extender) {
                    $object = $extender($object, $this);
                }
            }

            // Cache based on binding type
            if (isset($this->bindings[$abstract]['shared']) && $this->bindings[$abstract]['shared']) {
                $this->resolved[$abstract] = $object;
            } elseif (
                isset($this->bindings[$abstract]['scoped']) &&
                $this->bindings[$abstract]['scoped'] &&
                $this->currentScope !== null
            ) {
                $this->scopedInstances[$this->currentScope][$abstract] = $object;
            }

            array_pop($this->resolutionStack);
            return $object;
        } catch (\Exception $e) {
            array_pop($this->resolutionStack);
            throw $e;
        }
    }

    protected function isCircularDependency(string $abstract): bool
    {
        return in_array($abstract, $this->buildStack);
    }

    protected function formatResolutionStack(): string
    {
        if (empty($this->resolutionStack)) {
            return 'empty';
        }
        return implode(' -> ', $this->resolutionStack);
    }

    protected function getConcrete(string $abstract): mixed
    {
        if (isset($this->bindings[$abstract])) {
            return $this->bindings[$abstract]['concrete'];
        }

        return $abstract;
    }

    protected function build(mixed $concrete, array $parameters = []): mixed
    {
        if ($concrete instanceof Closure) {
            return $concrete($this, $parameters);
        }

        if (isset($this->reflectionCache[$concrete])) {
            $cached = $this->reflectionCache[$concrete];

            if ($cached['constructor'] === null) {
                return new $concrete;
            }

            $dependencies = $this->resolveDependencies($cached['parameters'], $parameters);
            return $cached['reflector']->newInstanceArgs($dependencies);
        }

        try {
            $reflector = new ReflectionClass($concrete);
        } catch (ReflectionException $e) {
            throw new ContainerException("Target class [{$concrete}] does not exist.", 0, $e);
        }

        if (!$reflector->isInstantiable()) {
            throw new ContainerException("Target [{$concrete}] is not instantiable.");
        }

        $constructor = $reflector->getConstructor();

        $this->reflectionCache[$concrete] = [
            'reflector' => $reflector,
            'constructor' => $constructor,
            'parameters' => $constructor ? $constructor->getParameters() : []
        ];

        if (is_null($constructor)) {
            return new $concrete;
        }

        $dependencies = $this->resolveDependencies($constructor->getParameters(), $parameters);

        return $reflector->newInstanceArgs($dependencies);
    }

    protected function resolveDependencies(array $dependencies, array $parameters = []): array
    {
        $results = [];

        foreach ($dependencies as $dependency) {
            $name = $dependency->getName();

            // Check if parameter was explicitly provided
            if (array_key_exists($name, $parameters)) {
                $results[] = $parameters[$name];
                continue;
            }

            $type = $dependency->getType();

            // No type hint - try default value or fail
            if ($type === null) {
                if ($dependency->isDefaultValueAvailable()) {
                    $results[] = $dependency->getDefaultValue();
                } else {
                    throw new ContainerException(
                        "Cannot resolve parameter [{$name}] in " .
                            $dependency->getDeclaringClass()->getName() .
                            " - no type hint and no default value provided" .
                            "\nResolution stack: " . $this->formatResolutionStack()
                    );
                }
                continue;
            }

            // Handle union types (e.g., string|int)
            if ($type instanceof ReflectionUnionType) {
                $results[] = $this->resolveUnionType($type, $dependency);
                continue;
            }

            // Handle named types (e.g., string, MyClass)
            if ($type instanceof ReflectionNamedType) {
                $results[] = $this->resolveNamedType($type, $dependency);
                continue;
            }

            // Fallback - shouldn't reach here in normal cases
            if ($dependency->isDefaultValueAvailable()) {
                $results[] = $dependency->getDefaultValue();
            } else {
                throw new ContainerException(
                    "Cannot resolve parameter [{$name}] - unknown type hint format" .
                        "\nResolution stack: " . $this->formatResolutionStack()
                );
            }
        }

        return $results;
    }

    /**
     * Resolve a named type (single type hint)
     */
    protected function resolveNamedType(ReflectionNamedType $type, \ReflectionParameter $dependency): mixed
    {
        $typeName = $type->getName();

        // Built-in types (string, int, array, etc.)
        if ($type->isBuiltin()) {
            if ($dependency->isDefaultValueAvailable()) {
                return $dependency->getDefaultValue();
            }

            // Special handling for nullable built-in types
            if ($type->allowsNull()) {
                return null;
            }

            throw new ContainerException(
                "Cannot resolve built-in parameter [{$dependency->getName()}] of type [{$typeName}] in " .
                    $dependency->getDeclaringClass()->getName() .
                    "\nResolution stack: " . $this->formatResolutionStack()
            );
        }

        // Class/interface type - resolve from container
        try {
            return $this->make($typeName);
        } catch (\Exception $e) {
            // If resolution fails and it's nullable, return null
            if ($type->allowsNull()) {
                return null;
            }

            // If has default value, use it
            if ($dependency->isDefaultValueAvailable()) {
                return $dependency->getDefaultValue();
            }

            throw $e;
        }
    }

    /**
     * Resolve a union type (e.g., string|int|MyClass)
     */
    protected function resolveUnionType(ReflectionUnionType $type, \ReflectionParameter $dependency): mixed
    {
        $types = $type->getTypes();

        // Try to resolve each type in order
        foreach ($types as $unionType) {
            if (!($unionType instanceof ReflectionNamedType)) {
                continue;
            }

            // Skip built-in types in union - we can't auto-resolve those
            if ($unionType->isBuiltin()) {
                continue;
            }

            // Try to resolve the class/interface type
            try {
                return $this->make($unionType->getName());
            } catch (\Exception $e) {
                // Continue to next type
                continue;
            }
        }

        // Couldn't resolve any type from the union
        // Check if parameter has a default value
        if ($dependency->isDefaultValueAvailable()) {
            return $dependency->getDefaultValue();
        }

        // Check if union allows null
        if ($type->allowsNull()) {
            return null;
        }

        // Build a readable type list for error message
        $typeNames = array_map(
            fn($t) => $t instanceof ReflectionNamedType ? $t->getName() : 'unknown',
            $types
        );

        throw new ContainerException(
            "Cannot resolve union type parameter [{$dependency->getName()}] (" .
                implode('|', $typeNames) . ") in " .
                $dependency->getDeclaringClass()->getName() .
                "\nResolution stack: " . $this->formatResolutionStack()
        );
    }


    /**
     * Compile the container for production
     */
    public function compile(): array
    {
        $compiled = [
            'bindings' => $this->bindings,
            'aliases' => $this->aliases,
            'abstractAliases' => $this->abstractAliases,
            'tags' => $this->tags,
            'singletons' => [],
            'reflectionCache' => $this->reflectionCache,
            'contextualBindings' => $this->contextualBindings,
            'deferredServices' => $this->deferredServices,
        ];

        // Pre-build all singletons
        foreach ($this->bindings as $abstract => $binding) {
            if ($binding['shared'] ?? false) {
                try {
                    $compiled['singletons'][$abstract] = $this->make($abstract);
                } catch (\Exception $e) {
                    // Skip singletons that can't be built yet
                }
            }
        }

        $this->compiled = true;
        $this->compiledData = $compiled;

        return $compiled;
    }

    /**
     * Load a compiled container
     */
    public function loadCompiled(array $compiled): void
    {
        $this->bindings = $compiled['bindings'] ?? [];
        $this->aliases = $compiled['aliases'] ?? [];
        $this->abstractAliases = $compiled['abstractAliases'] ?? [];
        $this->tags = $compiled['tags'] ?? [];
        $this->instances = $compiled['singletons'] ?? [];
        $this->reflectionCache = $compiled['reflectionCache'] ?? [];
        $this->contextualBindings = $compiled['contextualBindings'] ?? [];
        $this->deferredServices = $compiled['deferredServices'] ?? [];
        $this->compiled = true;
        $this->compiledData = $compiled;
    }

    /**
     * Export compiled container to a file
     */
    public function exportCompiled(string $filepath): void
    {
        $compiled = $this->compiled ? $this->compiledData : $this->compile();

        $export = '<?php return ' . var_export($compiled, true) . ';';
        file_put_contents($filepath, $export);
    }

    /**
     * Import compiled container from a file
     */
    public function importCompiled(string $filepath): void
    {
        if (!file_exists($filepath)) {
            throw new ContainerException("Compiled container file not found: {$filepath}");
        }

        $compiled = require $filepath;
        $this->loadCompiled($compiled);
    }

    /**
     * Check if container is compiled
     */
    public function isCompiled(): bool
    {
        return $this->compiled;
    }

    public function getAliases(string $abstract): array
    {
        return $this->abstractAliases[$abstract] ?? [];
    }

    public function clearReflectionCache(): void
    {
        $this->reflectionCache = [];
    }

    public function getResolutionStack(): array
    {
        return $this->resolutionStack;
    }

    /**
     * Get all bindings (for debugging)
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    /**
     * Get memory usage statistics
     */
    public function getMemoryStats(): array
    {
        return [
            'bindings' => count($this->bindings),
            'instances' => count($this->instances),
            'resolved' => count($this->resolved),
            'reflection_cache' => count($this->reflectionCache),
            'deferred' => count($this->deferredServices),
            'loaded_providers' => count($this->loadedProviders),
            'scoped_instances' => array_sum(array_map('count', $this->scopedInstances)),
        ];
    }

    public function flush(): void
    {
        $this->bindings = [];
        $this->instances = [];
        $this->resolved = [];
        $this->aliases = [];
        $this->abstractAliases = [];
        $this->contextualBindings = [];
        $this->scopedInstances = [];
        $this->reflectionCache = [];
        $this->buildStack = [];
        $this->resolutionStack = [];
        $this->tags = [];
        $this->deferredServices = [];
        $this->loadedProviders = [];
        $this->extenders = [];
        $this->methodBindings = [];
        $this->currentScope = null;
        $this->compiled = false;
        $this->compiledData = [];
    }
}
