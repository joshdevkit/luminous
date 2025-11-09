<?php

namespace Core\View;

use Core\Contracts\View\ViewFinderInterface;

class ViewFinder implements ViewFinderInterface
{
    protected array $locations = [];
    protected array $namespaces = [];
    protected array $extensions = ['blade.php', 'html'];
    protected array $cache = [];

    public function __construct(array $locations = [])
    {
        $this->locations = $locations;
    }

    public function find(string $view): string
    {
        if (isset($this->cache[$view])) {
            return $this->cache[$view];
        }

        // Check for namespaced view
        if (str_contains($view, '::')) {
            return $this->cache[$view] = $this->findNamespacedView($view);
        }

        return $this->cache[$view] = $this->findInLocations($view);
    }

    protected function findNamespacedView(string $view): string
    {
        [$namespace, $view] = explode('::', $view, 2);

        if (!isset($this->namespaces[$namespace])) {
            throw new ViewException("Namespace [{$namespace}] is not registered.");
        }

        return $this->findInPath($view, $this->namespaces[$namespace]);
    }

    protected function findInLocations(string $view): string
    {
        foreach ($this->locations as $location) {
            $path = $this->findInPath($view, $location);
            if ($path) {
                return $path;
            }
        }

        throw new ViewException("View [{$view}] not found.");
    }

    protected function findInPath(string $view, string $location): ?string
    {
        // Convert dot notation to directory path
        $viewPath = str_replace('.', DIRECTORY_SEPARATOR, $view);

        foreach ($this->extensions as $extension) {
            $path = $location . DIRECTORY_SEPARATOR . $viewPath . '.' . $extension;
            
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }

    public function addLocation(string $location): void
    {
        $this->locations[] = $location;
    }

    public function addNamespace(string $namespace, string $path): void
    {
        $this->namespaces[$namespace] = $path;
    }

    public function exists(string $view): bool
    {
        try {
            $this->find($view);
            return true;
        } catch (ViewException $e) {
            return false;
        }
    }
}

class ViewException extends \Exception {}