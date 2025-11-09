<?php

namespace Core\Contracts\Http;

use App\Entities\User;

/**
 * Interface RequestInterface
 *
 * Defines the contract for an HTTP request within the framework.
 * Provides methods to access HTTP method, URI, headers, query parameters,
 * request body, and authenticated user information.
 *
 * @package Core\Contracts\Http
 */
interface RequestInterface
{
    /**
     * Get the HTTP method (e.g., GET, POST, PUT, DELETE).
     *
     * @return string
     */
    public function getMethod(): string;

    /**
     * Determine if the current request method matches the given method.
     *
     * @param  string  $method  The HTTP method to compare (case-insensitive).
     * @return bool
     */
    public function isMethod(string $method): bool;

    /**
     * Get the full request URI.
     *
     * @return string
     */
    public function getUri(): string;

    /**
     * Get the request path (without query string).
     *
     * @return string
     */
    public function getPath(): string;

    /**
     * Get all query parameters from the request.
     *
     * @return array<string, mixed>
     */
    public function getQuery(): array;

    /**
     * Get all request headers.
     *
     * @return array<string, string>
     */
    public function getHeaders(): array;

    /**
     * Get a specific header value by name.
     *
     * @param  string  $name
     * @return string|null
     */
    public function getHeader(string $name): ?string;

    /**
     * Get the raw body of the request.
     *
     * @return mixed
     */
    public function getBody();

    /**
     * Retrieve a specific input value from the request body or query string.
     *
     * @param  string  $key
     * @param  mixed   $default
     * @return mixed
     */
    public function input(string $key, mixed $default = null): mixed;

    /**
     * Get all input data (body + query parameters) as an array.
     *
     * @return array<string, mixed>
     */
    public function all(): array;

    /**
     * Check if a key exists in the request data.
     *
     * @param  string  $key
     * @return bool
     */
    public function has(string $key): bool;

    /**
     * Get only specific keys from request.
     *
     * @param  array|string  $keys
     * @return array|string|null
     */
    public function only(array|string $keys): array|string|null;

    /**
     * Get all except specific keys from request.
     *
     * @param  array  $keys
     * @return array
     */
    public function except(array $keys): array;

    /**
     * Determine if the request expects a JSON response.
     * Checks Accept header and X-Requested-With header.
     *
     * @return bool
     */
    public function expectsJson(): bool;

    /**
     * Determine if the request is sending JSON data.
     * Checks Content-Type header.
     *
     * @return bool
     */
    public function isJson(): bool;

    /**
     * Determine if the request is an AJAX request.
     * Checks X-Requested-With header.
     *
     * @return bool
     */
    public function ajax(): bool;

    /**
     * Alias for ajax() method.
     *
     * @return bool
     */
    public function isAjax(): bool;

    /**
     * Determine if the request is over HTTPS.
     *
     * @return bool
     */
    public function isSecure(): bool;

    /**
     * Get the client IP address.
     *
     * @return string|null
     */
    public function ip(): ?string;

    /**
     * Get the user agent string.
     *
     * @return string|null
     */
    public function userAgent(): ?string;

    /**
     * Get the currently authenticated user (if any).
     *
     * @return User|null
     */
    public function user(): ?User;

    /**
     * Set the authenticated user manually (useful for middleware).
     *
     * @param  User  $user
     * @return void
     */
    public function setUser(User $user): void;
}