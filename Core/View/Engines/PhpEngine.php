<?php

namespace Core\View\Engines;

use Core\Contracts\View\EngineInterface;

class PhpEngine implements EngineInterface
{
    public function render(string $path, array $data = []): string
    {
        return $this->evaluatePath($path, $data);
    }

    protected function evaluatePath(string $path, array $data): string
    {
        ob_start();

        extract($data, EXTR_SKIP);

        try {
            include $path;
        } catch (\Throwable $e) {
            $this->handleException($e);
        }

        return ltrim(ob_get_clean());
    }

    protected function handleException(\Throwable $e): void
    {
        ob_get_clean();
        throw $e;
    }
}