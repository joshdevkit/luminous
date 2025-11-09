<?php

namespace Core\Http;

use App\Entities\User;
use Core\Contracts\Http\RequestInterface;

class Request implements RequestInterface
{
    protected array $query = [];
    protected array $request = [];
    protected array $server = [];
    protected array $headers = [];
    protected ?User $user = null;

    public function __construct(
        array $query = [],
        array $request = [],
        array $server = []
    ) {
        $this->query = $query;
        $this->request = $request;
        $this->server = $server;
        $this->headers = $this->extractHeaders($server);

        $this->user = $this->resolveUser();
    }

    public static function capture(): self
    {
        return new static($_GET, $_POST, $_SERVER);
    }

    protected function extractHeaders(array $server): array
    {
        $headers = [];

        foreach ($server as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $header = str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower(substr($key, 5)))));
                $headers[$header] = $value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'])) {
                $header = str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower($key))));
                $headers[$header] = $value;
            }
        }

        return $headers;
    }

    public function getMethod(): string
    {
        $method = strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');

        if ($method === 'POST' && isset($this->request['_method'])) {
            $spoofed = strtoupper($this->request['_method']);
            if (in_array($spoofed, ['PUT', 'PATCH', 'DELETE'])) {
                return $spoofed;
            }
        }

        return $method;
    }

    public function isMethod(string $method): bool
    {
        return strtoupper($this->getMethod()) === strtoupper($method);
    }

    public function getUri(): string
    {
        return $this->server['REQUEST_URI'] ?? '/';
    }

    public function getPath(): string
    {
        $uri = $this->getUri();
        $uri = preg_replace('#/+#', '/', $uri);
        $path = parse_url($uri, PHP_URL_PATH);

        if ($path === null || $path === false) {
            $path = strtok($uri, '?') ?: '/';
        }

        $path = '/' . ltrim($path, '/');
        return $path;
    }

    public function getQuery(): array
    {
        return $this->query;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getHeader(string $name): ?string
    {
        $name = str_replace(' ', '-', ucwords(str_replace('-', ' ', $name)));
        return $this->headers[$name] ?? null;
    }

    public function getBody(): array
    {
        return $this->request;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        $all = $this->all();
        return $all[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($this->query, $this->request);
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->all());
    }

    public function only(array|string $keys): array|string|null
    {
        $all = $this->all();

        if (is_string($keys)) {
            return $all[$keys] ?? null;
        }

        return array_intersect_key($all, array_flip($keys));
    }

    public function except(array $keys): array
    {
        $all = $this->all();
        return array_diff_key($all, array_flip($keys));
    }

    public function expectsJson(): bool
    {
        $accept = $this->getHeader('Accept') ?? '';
        if (stripos($accept, 'application/json') !== false) {
            return true;
        }

        if ($this->ajax()) {
            return true;
        }

        return false;
    }

    public function isJson(): bool
    {
        $contentType = $this->getHeader('Content-Type') ?? '';
        return stripos($contentType, 'application/json') !== false;
    }

    public function ajax(): bool
    {
        $xmlHttpRequest = $this->getHeader('X-Requested-With') ?? '';
        return strtolower(trim($xmlHttpRequest)) === 'xmlhttprequest';
    }

    public function isAjax(): bool
    {
        return $this->ajax();
    }

    public function isPjax(): bool
    {
        $pjax = $this->getHeader('X-Pjax');
        return $pjax !== null && $pjax !== '' && strtolower($pjax) !== 'false';
    }

    public function wantsJson(): bool
    {
        if ($this->expectsJson()) {
            return true;
        }

        if ($this->isJson()) {
            return true;
        }

        if (str_starts_with($this->getPath(), '/api')) {
            return true;
        }

        return false;
    }

    public function isSecure(): bool
    {
        if (isset($this->server['HTTPS']) && $this->server['HTTPS'] !== 'off') {
            return true;
        }

        $forwardedProto = $this->getHeader('X-Forwarded-Proto') ?? '';
        if (strtolower($forwardedProto) === 'https') {
            return true;
        }

        if (isset($this->server['SERVER_PORT']) && $this->server['SERVER_PORT'] == 443) {
            return true;
        }

        return false;
    }

    public function ip(): ?string
    {
        $headers = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($headers as $header) {
            if (isset($this->server[$header])) {
                $ip = $this->server[$header];

                if (str_contains($ip, ',')) {
                    $ip = trim(explode(',', $ip)[0]);
                }

                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return null;
    }

    public function userAgent(): ?string
    {
        return $this->getHeader('User-Agent') ?? $this->server['HTTP_USER_AGENT'] ?? null;
    }

    protected function resolveUser(): ?User
    {
        if (function_exists('auth') && auth()->user()) {
            return auth()->user();
        }

        return null;
    }

    public function user(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): void
    {
        $this->user = $user;
    }

    public function session()
    {
        return app('session');
    }

    public function __get(string $key): mixed
    {
        return $this->input($key);
    }

    public function __isset(string $key): bool
    {
        return $this->has($key);
    }

    public function __set(string $key, mixed $value): void
    {
        $this->request[$key] = $value;
    }

    public function __unset(string $key): void
    {
        unset($this->request[$key]);
        unset($this->query[$key]);
    }
}