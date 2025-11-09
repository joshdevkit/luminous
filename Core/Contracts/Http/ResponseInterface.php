<?php

namespace Core\Contracts\Http;

interface ResponseInterface
{
    public function setStatusCode(int $code): self;
    public function getStatusCode(): int;

    public function setHeader(string $name, string $value): self;

    /**
     * Alias for setHeader — adds or replaces a single header.
     */
    public function header(string $name, string $value): self;

    public function setContent(mixed $content): self;
    public function getContent(): string;

    public function send(): void;
}
