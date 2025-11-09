<?php

namespace Core\Contracts\View;

interface ViewFinderInterface
{
    public function find(string $view): string;
    public function addLocation(string $location): void;
    public function addNamespace(string $namespace, string $path): void;
    public function exists(string $view): bool;
}
