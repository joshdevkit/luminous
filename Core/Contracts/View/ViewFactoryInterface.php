<?php

namespace Core\Contracts\View;


interface ViewFactoryInterface
{
    public function make(string $view, array $data = []): ViewInterface;
    public function exists(string $view): bool;
    public function share(string $key, mixed $value): void;
    public function composer(string $view, callable $callback): void;
    public function addNamespace(string $namespace, string $path): void;
}