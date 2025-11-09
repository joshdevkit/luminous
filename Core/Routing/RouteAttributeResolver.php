<?php

namespace Core\Routing;

use Core\Routing\Attributes\Route;
use Core\Routing\Attributes\Get;
use Core\Routing\Attributes\Post;
use Core\Routing\Attributes\Put;
use Core\Routing\Attributes\Delete;
use Core\Routing\Attributes\Patch;

class RouteAttributeResolver
{
    public function resolveFromMethod(\ReflectionMethod $method): array
    {
        $routes = [];
        $attributes = $method->getAttributes();

        foreach ($attributes as $attribute) {
            $instance = $attribute->newInstance();

            if ($this->isRouteAttribute($instance)) {
                $routes[] = [
                    'method' => $this->getHttpMethod($instance),
                    'path' => $instance->path ?? $this->generatePathFromMethodName($method->getName()),
                ];
            }
        }

        return $routes;
    }

    private function isRouteAttribute(object $attribute): bool
    {
        return $attribute instanceof Route ||
            $attribute instanceof Get ||
            $attribute instanceof Post ||
            $attribute instanceof Put ||
            $attribute instanceof Delete ||
            $attribute instanceof Patch;
    }

    private function getHttpMethod(object $attribute): string
    {
        if (isset($attribute->method)) {
            return strtoupper($attribute->method);
        }

        return match (true) {
            $attribute instanceof Get => 'GET',
            $attribute instanceof Post => 'POST',
            $attribute instanceof Put => 'PUT',
            $attribute instanceof Delete => 'DELETE',
            $attribute instanceof Patch => 'PATCH',
            default => 'GET',
        };
    }

    private function generatePathFromMethodName(string $method): string
    {
        return '/' . strtolower(preg_replace('/[A-Z]/', '-$0', $method));
    }
}