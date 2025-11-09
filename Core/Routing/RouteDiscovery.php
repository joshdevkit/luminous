<?php

namespace Core\Routing;

class RouteDiscovery
{
    private Router $router;
    private array $controllerNamespaces = [];
    private bool $discovered = false;

    public function __construct(Router $router)
    {
        $this->router = $router;
    }

    public function addControllerNamespace(string $namespace, string $directory): void
    {
        $this->controllerNamespaces[] = [
            'namespace' => rtrim($namespace, '\\'),
            'directory' => rtrim($directory, '/'),
        ];
    }

    public function discover(): void
    {
        if ($this->discovered) {
            return;
        }

        foreach ($this->controllerNamespaces as $config) {
            $this->scanDirectory($config['directory'], $config['namespace']);
        }

        $this->discovered = true;
    }

    public function isDiscovered(): bool
    {
        return $this->discovered || empty($this->controllerNamespaces);
    }

    private function scanDirectory(string $directory, string $namespace): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $this->registerControllerFromFile($file->getPathname(), $namespace, $directory);
            }
        }
    }

    private function registerControllerFromFile(string $filepath, string $baseNamespace, string $baseDirectory): void
    {
        $relativePath = str_replace($baseDirectory, '', $filepath);
        $relativePath = str_replace(['/', '.php'], ['\\', ''], $relativePath);
        $controllerClass = $baseNamespace . $relativePath;

        if (class_exists($controllerClass)) {
            $this->router->registerController($controllerClass);
        }
    }
}