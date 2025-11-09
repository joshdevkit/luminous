<?php

namespace Core\Session;

use Core\Contracts\Session\SessionInterface;

class Session implements SessionInterface
{
    protected bool $started = false;
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'name' => config('app.name', 'FRAMEWORK-TOKEN'),
            'lifetime' => 120, // minutes
            'path' => '/',
            'domain' => null,
            'secure' => false,
            'http_only' => true,
            'same_site' => 'lax',
        ], $config);
    }

    public function start(): bool
    {
        if ($this->started) {
            return true;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            $this->started = true;
            $this->ageFlashData();
            return true;
        }

        $this->configureSession();

        if (session_start()) {
            $this->started = true;
            $this->ageFlashData();
            return true;
        }

        return false;
    }

    protected function configureSession(): void
    {
        ini_set('session.cookie_lifetime', $this->config['lifetime'] * 60);
        ini_set('session.cookie_path', $this->config['path']);
        ini_set('session.cookie_httponly', $this->config['http_only'] ? '1' : '0');
        ini_set('session.cookie_samesite', $this->config['same_site']);
        
        if ($this->config['domain']) {
            ini_set('session.cookie_domain', $this->config['domain']);
        }
        
        if ($this->config['secure']) {
            ini_set('session.cookie_secure', '1');
        }

        // Additional security settings
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');

        session_name($this->config['name']);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->ensureStarted();
        return $_SESSION[$key] ?? $default;
    }

    public function put(string $key, mixed $value): void
    {
        $this->ensureStarted();
        $_SESSION[$key] = $value;
    }

    public function has(string $key): bool
    {
        $this->ensureStarted();
        return isset($_SESSION[$key]);
    }

    public function forget(string $key): void
    {
        $this->ensureStarted();
        unset($_SESSION[$key]);
    }

    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);
        $this->forget($key);
        return $value;
    }

    public function flash(string $key, mixed $value): void
    {
        $this->ensureStarted();
        
        if (!isset($_SESSION['_flash.new'])) {
            $_SESSION['_flash.new'] = [];
        }
        
        $_SESSION['_flash.new'][$key] = $value;
    }

    public function all(): array
    {
        $this->ensureStarted();
        return $_SESSION;
    }

    public function regenerate(bool $deleteOldSession = true): bool
    {
        $this->ensureStarted();
        
        // Preserve flash data and CSRF token during regeneration
        $token = $this->get('_token');
        $flashNew = $this->get('_flash.new', []);
        $flashOld = $this->get('_flash.old', []);
        
        $result = session_regenerate_id($deleteOldSession);
        
        if ($result) {
            // Restore preserved data
            if ($token) {
                $this->put('_token', $token);
            }
            if (!empty($flashNew)) {
                $this->put('_flash.new', $flashNew);
            }
            if (!empty($flashOld)) {
                $this->put('_flash.old', $flashOld);
            }
        }
        
        return $result;
    }

    public function invalidate(): bool
    {
        $this->ensureStarted();
        
        $_SESSION = [];
        
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        $this->started = false;
        return session_destroy();
    }

    public function getId(): string
    {
        return session_id();
    }

    public function setId(string $id): void
    {
        if ($this->started) {
            throw new \RuntimeException('Cannot set session ID after session has started.');
        }
        session_id($id);
    }

    public function isStarted(): bool
    {
        return $this->started;
    }

    /**
     * Ensure session is started before accessing data
     *
     * @return void
     */
    protected function ensureStarted(): void
    {
        if (!$this->started) {
            $this->start();
        }
    }

    /**
     * Age flash data - remove old flash and prepare new flash for next request
     *
     * @return void
     */
    protected function ageFlashData(): void
    {
        // Remove old flash data
        if (isset($_SESSION['_flash.old'])) {
            foreach ($_SESSION['_flash.old'] as $key => $value) {
                unset($_SESSION[$key]);
            }
            unset($_SESSION['_flash.old']);
        }

        // Age current flash data
        if (isset($_SESSION['_flash.new'])) {
            // Move flash data to main session for current request
            foreach ($_SESSION['_flash.new'] as $key => $value) {
                $_SESSION[$key] = $value;
            }
            
            // Mark current flash as old for next request
            $_SESSION['_flash.old'] = $_SESSION['_flash.new'];
            unset($_SESSION['_flash.new']);
        }
    }

    /**
     * Get the CSRF token
     *
     * @return string|null
     */
    public function token(): ?string
    {
        return $this->get('_token');
    }


    /**
     * refl
     */

    /**
     * Regenerate the CSRF token
     *
     * @return string
     */
    public function regenerateToken(): string
    {
        $token = bin2hex(random_bytes(32));
        $this->put('_token', $token);
        return $token;
    }
}