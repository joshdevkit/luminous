<?php

namespace Core\Contracts\View;

interface ViewInterface
{
    public function render(): string;
    public function with(string $key, mixed $value): self;
    public function withData(array $data): self;
}