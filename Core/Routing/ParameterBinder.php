<?php

namespace Core\Routing;

use Core\Contracts\Container\ContainerInterface;
use Core\Contracts\Http\RequestInterface;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionNamedType;

class ParameterBinder
{
    protected ContainerInterface $container;
    protected RequestInterface $request;
    
    public function __construct(RequestInterface $request, ContainerInterface $container)
    {
        $this->request = $request;
        $this->container = $container;
    }

    public function bindParameters(ReflectionMethod $method, array $routeParams = []): array
    {
        $parameters = $method->getParameters();
        $boundParams = [];

        foreach ($parameters as $param) {
            $boundParams[] = $this->bindParameter($param, $routeParams);
        }

        return $boundParams;
    }

    protected function bindParameter(ReflectionParameter $param, array $routeParams): mixed
    {
        $paramName = $param->getName();
        
        // Check if parameter has a class type hint
        $type = $param->getType();
        if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
            $className = $type->getName();

            // Check if it's the Request object itself
            if (is_a($className, RequestInterface::class, true)) {
                return $this->request;
            }

            // Check if it's a BaseForm subclass - bind from request body
            if (is_subclass_of($className, \Core\Routing\BaseForm::class)) {
                return $this->bindFormFromRequest($className);
            }

            // Check if there's a route parameter value for this parameter
            if (isset($routeParams[$paramName])) {
                // Attempt automatic model binding
                return $this->resolveModel($className, $routeParams[$paramName]);
            }

            // Try to resolve from container
            if ($this->container->has($className)) {
                return $this->container->make($className);
            }

            // Try to instantiate from request data (for other FromBody-style bindings)
            return $this->bindFromBody($param);
        }

        // Check if it's in route parameters
        if (isset($routeParams[$paramName])) {
            return $this->castValue($routeParams[$paramName], $param);
        }

        // Try to get from query or post data
        $value = $this->request->input($paramName);
        if ($value !== null) {
            return $this->castValue($value, $param);
        }

        // Return default value if available
        if ($param->isDefaultValueAvailable()) {
            return $param->getDefaultValue();
        }

        // Return null if nullable
        if ($param->allowsNull()) {
            return null;
        }

        throw new \InvalidArgumentException("Cannot bind parameter: {$paramName}");
    }

    /**
     * Bind a BaseForm subclass from request data
     */
    protected function bindFormFromRequest(string $formClass): object
    {
        // Get all request data (handles JSON, form data, etc.)
        $data = $this->getRequestData();
        
        // BaseForm constructor handles validation and data binding automatically
        return new $formClass($data);
    }

    /**
     * Resolve a model instance from a route parameter value
     */
    protected function resolveModel(string $className, mixed $value): object
    {
        // Try using findOrFail() method first (preferred - throws proper errors)
        try {
            $model = $className::findOrFail($value);

            if ($model !== null) {
                return $model;
            }
        } catch (\RuntimeException $e) {
            // Re-throw with the proper error message
            throw $e;
        } catch (\TypeError | \BadMethodCallException $e) {
            // findOrFail() doesn't exist, try find()
        }

        // Try using find() method
        try {
            $model = $className::find($value);

            if ($model !== null) {
                return $model;
            }

            // Get the primary key name from a temporary instance
            $tempInstance = new $className();
            $primaryKey = method_exists($tempInstance, 'getPrimaryKey')
                ? $tempInstance->getPrimaryKey()
                : 'id';

            throw new \RuntimeException("Model {$className} with {$primaryKey} {$value} not found");
        } catch (\TypeError $e) {
            // find() doesn't exist or has wrong signature, try next approach
        }

        // Try using findOne() method
        try {
            $model = $className::findOne($value);

            if ($model !== null) {
                return $model;
            }

            throw new \RuntimeException("Model {$className} with ID {$value} not found");
        } catch (\TypeError | \BadMethodCallException $e) {
            // findOne() doesn't exist, try next approach
        }

        // Try query builder pattern with where()->first()
        try {
            $model = $className::where('id', $value)->first();

            if ($model !== null) {
                return $model;
            }

            throw new \RuntimeException("Model {$className} with ID {$value} not found");
        } catch (\Exception $e) {
            // where() doesn't work, fall through to error
        }

        throw new \RuntimeException(
            "Cannot resolve model {$className}. Class must implement findOrFail(), find(), findOne(), or where() method. " .
                "Error: " . $e->getMessage()
        );
    }

    protected function bindFromBody(ReflectionParameter $param): mixed
    {
        $type = $param->getType();

        if (!$type instanceof ReflectionNamedType) {
            throw new \InvalidArgumentException("FromBody parameter must have a type hint");
        }

        $className = $type->getName();

        // Get data from request
        $data = $this->getRequestData();

        // Create and populate the object
        return $this->hydrate($className, $data);
    }

    protected function getRequestData(): array
    {
        $headers = $this->request->getHeaders();
        $contentType = $headers['Content-Type'][0] ?? $headers['content-type'][0] ?? '';

        // Handle JSON
        if (str_contains($contentType, 'application/json')) {
            $body = $this->request->getBody();
            return json_decode($body, true) ?? [];
        }

        // Handle form data (default)
        return $this->request->all();
    }

    protected function hydrate(string $className, array $data): object
    {
        $reflection = new \ReflectionClass($className);
        
        // Try constructor first if it has parameters
        $constructor = $reflection->getConstructor();
        if ($constructor && $constructor->getNumberOfParameters() > 0) {
            $instance = $this->hydrateViaConstructor($reflection, $data);
        } else {
            // Create instance without constructor or with empty constructor
            $instance = $reflection->newInstance();
        }

        // Always set properties after construction (supports both styles)
        $this->setProperties($instance, $reflection, $data);

        return $instance;
    }

    protected function hydrateViaConstructor(\ReflectionClass $class, array $data): object
    {
        $constructor = $class->getConstructor();
        $params = [];

        foreach ($constructor->getParameters() as $param) {
            $paramName = $param->getName();
            $value = $data[$paramName] ?? null;

            if ($value === null && $param->isDefaultValueAvailable()) {
                $params[] = $param->getDefaultValue();
            } elseif ($value === null && $param->allowsNull()) {
                $params[] = null;
            } else {
                $params[] = $this->castValue($value, $param);
            }
        }

        return $class->newInstanceArgs($params);
    }

    protected function setProperties(object $instance, \ReflectionClass $class, array $data): void
    {
        foreach ($data as $key => $value) {
            // Try to set via __set magic method (works even if property doesn't exist)
            if (method_exists($instance, '__set')) {
                $instance->$key = $value;
                continue;
            }

            // Try public property
            if ($class->hasProperty($key)) {
                $property = $class->getProperty($key);
                if ($property->isPublic()) {
                    $typedValue = $this->castPropertyValue($value, $property);
                    $property->setValue($instance, $typedValue);
                    continue;
                }
            }

            // Try setter method
            $setter = 'set' . ucfirst($key);
            if ($class->hasMethod($setter)) {
                $method = $class->getMethod($setter);
                if ($method->isPublic()) {
                    $method->invoke($instance, $value);
                    continue;
                }
            }

            // If none of the above work, try to set it anyway (dynamic property)
            $instance->$key = $value;
        }
    }

    protected function castValue(mixed $value, ReflectionParameter $param): mixed
    {
        $type = $param->getType();

        if (!$type instanceof ReflectionNamedType) {
            return $value;
        }

        return match ($type->getName()) {
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'string' => (string) $value,
            'array' => is_array($value) ? $value : [$value],
            default => $value
        };
    }

    protected function castPropertyValue(mixed $value, \ReflectionProperty $property): mixed
    {
        $type = $property->getType();

        if (!$type instanceof ReflectionNamedType) {
            return $value;
        }

        return match ($type->getName()) {
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'string' => (string) $value,
            'array' => is_array($value) ? $value : [$value],
            default => $value
        };
    }
}