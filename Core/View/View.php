<?php

namespace Core\View;

use Core\Contracts\View\ViewInterface;
use Core\Contracts\View\EngineInterface;

class View implements ViewInterface
{
    protected EngineInterface $engine;
    protected string $path;
    protected array $data = [];

    public function __construct(EngineInterface $engine, string $path, array $data = [])
    {
        $this->engine = $engine;
        $this->path = $path;
        $this->data = $data;
    }

    public function render(): string
    {
        return $this->engine->render($this->path, $this->data);
    }

    public function with(string $key, mixed $value): self
    {
        $this->data[$key] = $value;
        return $this;
    }

    public function withData(array $data): self
    {
        $this->data = array_merge($this->data, $data);
        return $this;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function __toString(): string
    {
        return $this->render();
    }
}