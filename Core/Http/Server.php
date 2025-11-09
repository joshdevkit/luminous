<?php

namespace Core\Http;

class Server
{
    protected array $server;

    protected const BASE = '/';
    protected const DEFAULT = '';
    protected const API_PREFIX = '/api';
    protected const JSON_PREFIX = 'application/json';
    protected const REQUEST_URI = 'REQUEST_URI';
    protected const HTTP_ACCEPT = 'HTTP_ACCEPT';
    protected const CONTENT_TYPE = 'CONTENT_TYPE';

    public function __construct(array $server = [])
    {
        $this->server = $server ?: $_SERVER;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->server[$key] ?? $default;
    }

    public function all(): array
    {
        return $this->server;
    }

    public function requestUri(): string
    {
        return $this->get(self::REQUEST_URI, self::BASE);
    }

    public function accept(): string
    {
        return $this->get(self::HTTP_ACCEPT, self::DEFAULT);
    }

    public function contentType(): string
    {
        return $this->get(self::CONTENT_TYPE, self::DEFAULT);
    }

    public function isApiRequest(): bool
    {
        $path = $this->requestUri();

        if (str_starts_with($path, self::API_PREFIX)) {
            return true;
        }

        $accept = $this->accept();
        if (str_contains($accept, self::JSON_PREFIX)) {
            return true;
        }

        $contentType = $this->contentType();
        if (str_contains($contentType, self::JSON_PREFIX)) {
            return true;
        }

        return false;
    }
}
