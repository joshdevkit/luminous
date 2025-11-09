<?php

namespace Core\Http;

use Core\Contracts\Http\ResponseInterface;
use Core\Contracts\Session\SessionInterface;
use Core\Support\ErrorBag;

/**
 * Class RedirectResponse
 *
 * A specialized response for HTTP redirects with flash data support.
 */
class RedirectResponse implements ResponseInterface
{
    protected string $url;
    protected int $statusCode;
    protected array $headers = [];
    protected ?SessionInterface $session = null;

    public function __construct(string $url, int $statusCode = 302, array $headers = [])
    {
        $this->url = $url;
        $this->statusCode = $statusCode;
        $this->headers = $headers;
        $this->headers['Location'] = $url;
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
    // Core ResponseInterface implementation
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

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function setContent(mixed $content): self
    {
        // Redirects don't have content body
        return $this;
    }

    public function getContent(): string
    {
        return '';
    }

    public function send(): void
    {
        $this->sendHeaders();
    }

    protected function sendHeaders(): void
    {
        if (headers_sent()) {
            return;
        }

        $statusTexts = [
            301 => 'Moved Permanently',
            302 => 'Found',
            303 => 'See Other',
            307 => 'Temporary Redirect',
            308 => 'Permanent Redirect',
        ];

        $statusText = $statusTexts[$this->statusCode] ?? 'Found';
        header(sprintf('HTTP/1.1 %d %s', $this->statusCode, $statusText));

        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}", false);
        }
    }

    // ───────────────────────────────────────────────
    // Flash messaging
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

    /**
     * Attach a success flash message.
     * 
     * Usage:
     *   ->withSuccess('Login successful!')
     *   ->withSuccess('message', 'Login successful!')
     */
    public function withSuccess(string $keyOrMessage, ?string $message = null): self
    {
        $session = $this->getSessionInstance();

        if ($message === null) {
            $session->flash('success', $keyOrMessage);
        } else {
            $session->flash($keyOrMessage, $message);
        }

        return $this;
    }

    /**
     * Attach an info flash message.
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
     * Attach an error flash message.
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
     *   ->withErrors($form->errors())
     *   ->withErrors(['email' => 'Invalid email'])
     *   ->withErrors('Something went wrong')
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
    // Static factory methods
    // ───────────────────────────────────────────────

    /**
     * Create a redirect to a URL
     */
    public static function to(string $url, int $status = 302): self
    {
        return new static($url, $status);
    }

    /**
     * Create a redirect back to the previous page
     */
    public static function back(int $status = 302): self
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '/';
        return new static($referer, $status);
    }

    /**
     * Create a redirect to a route (if you have named routes)
     */
    public static function route(string $name, array $parameters = [], int $status = 302): self
    {
        // This would require a route resolver
        // For now, just redirect to the name as-is
        return new static($name, $status);
    }

    // ───────────────────────────────────────────────
    // Redirect type helpers
    // ───────────────────────────────────────────────

    /**
     * Make this a permanent redirect (301)
     */
    public function permanent(): self
    {
        return $this->setStatusCode(301);
    }

    /**
     * Make this a temporary redirect (302)
     */
    public function temporary(): self
    {
        return $this->setStatusCode(302);
    }

    /**
     * Make this a see other redirect (303)
     */
    public function seeOther(): self
    {
        return $this->setStatusCode(303);
    }

    /**
     * Make this a temporary redirect (307)
     */
    public function temporaryRedirect(): self
    {
        return $this->setStatusCode(307);
    }

    /**
     * Make this a permanent redirect (308)
     */
    public function permanentRedirect(): self
    {
        return $this->setStatusCode(308);
    }

    /**
     * Get the redirect URL
     */
    public function getTargetUrl(): string
    {
        return $this->url;
    }

    /**
     * Set a new redirect URL
     */
    public function setTargetUrl(string $url): self
    {
        $this->url = $url;
        $this->headers['Location'] = $url;
        return $this;
    }

    // ───────────────────────────────────────────────
    // Additional header helpers
    // ───────────────────────────────────────────────

    /**
     * Add a header to the redirect
     */
    public function withHeader(string $name, string $value): self
    {
        return $this->setHeader($name, $value);
    }

    /**
     * Add multiple headers
     */
    public function withHeaders(array $headers): self
    {
        foreach ($headers as $name => $value) {
            $this->setHeader($name, (string) $value);
        }
        return $this;
    }

    /**
     * Set a cookie with the redirect
     */
    public function withCookie(
        string $name,
        string $value,
        int $minutes = 0,
        string $path = '/',
        ?string $domain = null,
        bool $secure = false,
        bool $httpOnly = true
    ): self {
        $expire = $minutes > 0 ? time() + ($minutes * 60) : 0;

        setcookie($name, $value, [
            'expires' => $expire,
            'path' => $path,
            'domain' => $domain ?? '',
            'secure' => $secure,
            'httponly' => $httpOnly,
            'samesite' => 'Lax',
        ]);

        return $this;
    }
}