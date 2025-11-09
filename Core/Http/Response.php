<?php

namespace Core\Http;

use Core\Contracts\Http\ResponseInterface;
use Core\Contracts\View\ViewInterface;
use Core\Contracts\Session\SessionInterface;
use Core\Support\ErrorBag;

/**
 * Class Response
 *
 * A fluent, immutable-style HTTP response builder.
 * Supports chaining, redirects, JSON, HTML, and flash messaging.
 */
class Response implements ResponseInterface
{
    protected int $statusCode = 200;
    protected array $headers = [];
    protected string $content = '';
    protected ?SessionInterface $session = null;

    /** @var array<string, string> */
    protected static array $statusTexts = [
        // 2xx Success
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        204 => 'No Content',

        // 3xx Redirection
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',

        // 4xx Client Errors
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        413 => 'Payload Too Large',
        415 => 'Unsupported Media Type',
        422 => 'Unprocessable Entity',
        429 => 'Too Many Requests',

        // 5xx Server Errors
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
    ];

    public function __construct(mixed $content = '', int $statusCode = 200, array $headers = [])
    {
        $this->setContent($content)
            ->setStatusCode($statusCode);

        foreach ($headers as $name => $value) {
            $this->setHeader($name, $value);
        }
    }

    /**
     * Set the session instance for flash data
     */
    public function setSession(SessionInterface $session): self
    {
        $this->session = $session;
        return $this;
    }

    // ───────────────────────────────────────────────
    // Core setup
    // ───────────────────────────────────────────────

    public function setStatusCode(int $code): self
    {
        $this->statusCode = $code;
        return $this;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function setHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function header(string $name, string $value): self
    {
        return $this->setHeader($name, $value);
    }

    /**
     * Set a single header (fluent alias)
     */
    public function withHeader(string $name, string $value): self
    {
        return $this->setHeader($name, $value);
    }

    /**
     * Set multiple headers at once
     */
    public function withHeaders(array $headers): self
    {
        foreach ($headers as $name => $value) {
            $this->setHeader($name, (string) $value);
        }
        return $this;
    }

    /**
     * Remove a header
     */
    public function withoutHeader(string $name): self
    {
        unset($this->headers[$name]);
        return $this;
    }

    /**
     * Check if header exists
     */
    public function hasHeader(string $name): bool
    {
        return isset($this->headers[$name]);
    }

    /**
     * Get a specific header value
     */
    public function getHeader(string $name): ?string
    {
        return $this->headers[$name] ?? null;
    }

    /**
     * Get all headers
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function setContent(mixed $content): self
    {
        if ($content instanceof ViewInterface) {
            $this->content = $content->render();
            $this->setHeader('Content-Type', 'text/html; charset=UTF-8');
        } elseif (is_array($content) || is_object($content)) {
            $this->content = json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $this->setHeader('Content-Type', 'application/json');
        } else {
            $this->content = (string) $content;
        }

        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    // ───────────────────────────────────────────────
    // Output
    // ───────────────────────────────────────────────

    public function send(): void
    {
        $this->sendHeaders();
        $this->sendContent();

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
    }

    protected function sendHeaders(): void
    {
        if (headers_sent()) {
            return;
        }

        $statusText = static::$statusTexts[$this->statusCode] ?? 'Unknown';
        header(sprintf('HTTP/1.1 %d %s', $this->statusCode, $statusText));

        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}", false);
        }
    }

    protected function sendContent(): void
    {
        echo $this->content;
    }

    // ───────────────────────────────────────────────
    // Factory helpers
    // ───────────────────────────────────────────────

    /**
     * Flash input data to session
     */
    public function withInput(array $input = []): self
    {
        $session = $this->getSessionInstance();

        // If no input provided, use $_POST
        $input = $input ?: $_POST;

        // Remove sensitive fields
        $filtered = array_diff_key($input, array_flip([
            'password',
            'password_confirmation',
            'token',
            '_token',
            '_csrf_token',
        ]));

        $session->flash('old_input', $filtered);

        return $this;
    }

    public static function view(string $view, array $data = [], int $status = 200, array $headers = []): self
    {
        if (function_exists('view')) {
            // Use global view() helper if available
            $content = view($view, $data);
        } else {
            // Fallback if view() helper not defined
            $viewFactory = app(ViewInterface::class);
            $content = $viewFactory->make($view, $data);
        }

        return new static($content, $status, $headers);
    }

    public static function json(mixed $data, int $status = 200): self
    {
        return new static($data, $status, ['Content-Type' => 'application/json']);
    }

    public static function redirect(?string $url = null, int $status = 302): self
    {
        // Fallback to referer or root if no URL provided
        if (empty($url)) {
            $url = $_SERVER['HTTP_REFERER'] ?? '/';
        }

        return new static('', $status, ['Location' => $url]);
    }

    public static function back(int $status = 302): self
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '/';
        $response = static::redirect($referer, $status);
        return $response;
    }

    /**
     * Fluent redirect helper for chaining.
     *
     * Example:
     *   return Response::to('/dashboard');
     *   return redirect()->to('/login');
     */
    public static function to(?string $url = null, int $status = 302): self
    {
        return static::redirect($url, $status);
    }

    // ───────────────────────────────────────────────
    // Flash messaging
    // ───────────────────────────────────────────────

    /**
     * Attach a success flash message.
     * 
     * Usage:
     *   ->withSuccess('Login successful!')              // Uses default 'success' key
     *   ->withSuccess('message', 'Login successful!')   // Uses custom key
     */
    public function withSuccess(string $keyOrMessage, ?string $message = null): self
    {
        $session = $this->getSessionInstance();

        // If only one argument, use it as message with default 'success' key
        if ($message === null) {
            $session->flash('success', $keyOrMessage);
        } else {
            // Two arguments: first is key, second is message
            $session->flash($keyOrMessage, $message);
        }

        return $this;
    }

    /**
     * Attach an info flash message.
     * 
     * Usage:
     *   ->withInfo('Please verify your email')
     *   ->withInfo('notification', 'Please verify your email')
     */
    public function withInfo(string $keyOrMessage, ?string $message = null): self
    {
        $session = $this->getSessionInstance();

        if ($message === null) {
            $session->flash('info', $keyOrMessage);
        } else {
            $session->flash($keyOrMessage, $message);
        }

        return $this;
    }

    /**
     * Attach a warning flash message.
     * 
     * Usage:
     *   ->withWarning('Your session will expire soon')
     *   ->withWarning('alert', 'Your session will expire soon')
     */
    public function withWarning(string $keyOrMessage, ?string $message = null): self
    {
        $session = $this->getSessionInstance();

        if ($message === null) {
            $session->flash('warning', $keyOrMessage);
        } else {
            $session->flash($keyOrMessage, $message);
        }

        return $this;
    }

    /**
     * Attach an error flash message (different from validation errors).
     * 
     * Usage:
     *   ->withError('Something went wrong')
     *   ->withError('system_error', 'Something went wrong')
     */
    public function withError(string $keyOrMessage, ?string $message = null): self
    {
        $session = $this->getSessionInstance();

        if ($message === null) {
            $session->flash('error', $keyOrMessage);
        } else {
            $session->flash($keyOrMessage, $message);
        }

        return $this;
    }

    /**
     * Attach arbitrary flash data
     */
    public function with(string $key, mixed $value): self
    {
        $session = $this->getSessionInstance();
        $session->flash($key, $value);
        return $this;
    }

    /**
     * Attach validation errors via ErrorBag.
     * 
     * Usage:
     *   ->withErrors($form->errors())                    // ErrorBag instance
     *   ->withErrors(['email' => 'Invalid email'])       // Array of errors
     *   ->withErrors('Something went wrong')             // Single error message
     */
    public function withErrors(ErrorBag|array|string $errors): self
    {
        $session = $this->getSessionInstance();

        $bag = $errors instanceof ErrorBag ? $errors : new ErrorBag();

        if (is_string($errors)) {
            $bag->add('general', $errors);
        } elseif (is_array($errors)) {
            foreach ($errors as $field => $messages) {
                if (is_array($messages)) {
                    foreach ($messages as $message) {
                        $bag->add($field, $message);
                    }
                } else {
                    $bag->add(is_string($field) ? $field : 'general', (string) $messages);
                }
            }
        }

        $session->flash('errors', $bag);
        // dd($session->get('errors'));
        return $this;
    }

    /**
     * Get session instance (fallback to global if not injected)
     */
    protected function getSessionInstance(): SessionInterface
    {
        if ($this->session) {
            return $this->session;
        }

        // Fallback to global app container
        if (function_exists('app')) {
            return app()->get('session');
        }

        // Last resort: use old session_start method
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        throw new \RuntimeException('Session not available');
    }

    // ───────────────────────────────────────────────
    // Common header helpers
    // ───────────────────────────────────────────────

    /**
     * Set cache control headers
     */
    public function withCache(int $seconds): self
    {
        return $this->withHeaders([
            'Cache-Control' => "public, max-age={$seconds}",
            'Expires' => gmdate('D, d M Y H:i:s', time() + $seconds) . ' GMT',
        ]);
    }

    /**
     * Disable caching
     */
    public function withoutCache(): self
    {
        return $this->withHeaders([
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    /**
     * Set CORS headers
     */
    public function withCors(
        string|array $origins = '*',
        array $methods = ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
        array $headers = ['Content-Type', 'Authorization']
    ): self {
        $origin = is_array($origins) ? implode(', ', $origins) : $origins;

        return $this->withHeaders([
            'Access-Control-Allow-Origin' => $origin,
            'Access-Control-Allow-Methods' => implode(', ', $methods),
            'Access-Control-Allow-Headers' => implode(', ', $headers),
        ]);
    }

    /**
     * Set content disposition for downloads
     */
    public function withDownload(string $filename): self
    {
        return $this->withHeader(
            'Content-Disposition',
            'attachment; filename="' . $filename . '"'
        );
    }

    /**
     * Set content type
     */
    public function withContentType(string $type, string $charset = 'UTF-8'): self
    {
        return $this->withHeader('Content-Type', "{$type}; charset={$charset}");
    }

    /**
     * Add security headers
     */
    public function withSecurityHeaders(): self
    {
        return $this->withHeaders([
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'X-XSS-Protection' => '1; mode=block',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
        ]);
    }

    /**
     * Set HTTP Basic Auth challenge
     */
    public function withAuthChallenge(string $realm = 'Restricted Area'): self
    {
        return $this->withHeader('WWW-Authenticate', "Basic realm=\"{$realm}\"")
            ->setStatusCode(401);
    }

    /**
     * Add ETag header
     */
    public function withETag(string $etag): self
    {
        return $this->withHeader('ETag', "\"{$etag}\"");
    }

    /**
     * Set refresh header (meta refresh)
     */
    public function withRefresh(int $seconds, ?string $url = null): self
    {
        $value = (string) $seconds;
        if ($url) {
            $value .= "; url={$url}";
        }
        return $this->withHeader('Refresh', $value);
    }

    // ───────────────────────────────────────────────
    // Transform helpers
    // ───────────────────────────────────────────────

    public function toJson(): self
    {
        $this->content = json_encode(
            ['data' => $this->content, 'status' => $this->statusCode],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        $this->setHeader('Content-Type', 'application/json');
        return $this;
    }

    public function toHtml(): self
    {
        $this->setHeader('Content-Type', 'text/html; charset=UTF-8');
        return $this;
    }

    /**
     * Convert to XML response
     */
    public function toXml(): self
    {
        $this->setHeader('Content-Type', 'application/xml; charset=UTF-8');
        return $this;
    }

    /**
     * Convert to plain text
     */
    public function toText(): self
    {
        $this->setHeader('Content-Type', 'text/plain; charset=UTF-8');
        return $this;
    }

    // ───────────────────────────────────────────────
    // Status code helpers
    // ───────────────────────────────────────────────

    public function ok(): self
    {
        return $this->setStatusCode(200);
    }

    public function created(): self
    {
        return $this->setStatusCode(201);
    }

    public function accepted(): self
    {
        return $this->setStatusCode(202);
    }

    public function noContent(): self
    {
        return $this->setStatusCode(204);
    }

    public function movedPermanently(): self
    {
        return $this->setStatusCode(301);
    }

    public function found(): self
    {
        return $this->setStatusCode(302);
    }

    public function notModified(): self
    {
        return $this->setStatusCode(304);
    }

    public function badRequest(): self
    {
        return $this->setStatusCode(400);
    }

    public function unauthorized(): self
    {
        return $this->setStatusCode(401);
    }

    public function forbidden(): self
    {
        return $this->setStatusCode(403);
    }

    public function notFound(): self
    {
        return $this->setStatusCode(404);
    }

    public function methodNotAllowed(): self
    {
        return $this->setStatusCode(405);
    }

    public function conflict(): self
    {
        return $this->setStatusCode(409);
    }

    public function unprocessableEntity(): self
    {
        return $this->setStatusCode(422);
    }

    public function tooManyRequests(): self
    {
        return $this->setStatusCode(429);
    }

    public function serverError(): self
    {
        return $this->setStatusCode(500);
    }

    public function serviceUnavailable(): self
    {
        return $this->setStatusCode(503);
    }
}
