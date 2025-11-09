<?php

namespace Core\Contracts\View;

interface EngineInterface
{
    public function render(string $path, array $data = []): string;
}