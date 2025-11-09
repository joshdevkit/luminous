<?php

namespace Core\Auth;

use App\Entities\User;
use Core\Contracts\Auth\AuthManagerContract;
use Core\Session\Session;
use Core\Database\Model;
use Core\Facades\Hash;
use Core\Framework\Application;
use RuntimeException;

class AuthManager implements AuthManagerContract
{
    protected Session $session;
    protected ?User $user = null;
    protected string $userEntity;
    protected string $sessionKey = 'auth_id';

    public function __construct(Session $session, string $userEntity = 'App\\Entities\\User')
    {
        $this->session = $session;
        $this->userEntity = $userEntity;
    }

    /**
     * Check if authentication is enabled in the application
     * 
     * @return bool
     */
    protected function isAuthEnabled(): bool
    {
        $app = Application::init();
        return $app && $app->isAuthEnabled();
    }

    /**
     * Ensure authentication is enabled (throws exception if not)
     * 
     * @throws RuntimeException
     */
    protected function ensureAuthEnabled(): void
    {
        if (!$this->isAuthEnabled()) {
            throw new RuntimeException(
                'Authentication is not enabled. Please call $app->useAuthentication() in bootstrap/app.php to enable authentication features.'
            );
        }
    }

    /**
     * Attempt to authenticate a user using dynamic credentials.
     */
    public function attempt(array $credentials): bool
    {
        $this->ensureAuthEnabled();

        // Ensure session is started
        $this->session->start();

        // Ensure credentials contain at least a password field
        if (!isset($credentials['password'])) {
            return false;
        }

        $password = $credentials['password'];
        unset($credentials['password']); // remove password before querying

        // If there are no other credentials, fail early
        if (empty($credentials)) {
            return false;
        }

        // Fetch user dynamically based on provided credential fields
        $user = $this->getUserByCredentials($credentials);

        if (!$user || !Hash::check($password, $user->password)) {
            return false;
        }

        $this->login($user);
        return true;
    }

    /**
     * Log in a user
     */
    public function login(object $user): void
    {
        $this->ensureAuthEnabled();

        $this->session->start();

        // Get the primary key value using the model's method
        $userId = ($user instanceof Model) ? $user->getKey() : $user->id;
        
        // Ensure user has an ID
        if (empty($userId)) {
            throw new RuntimeException('User object must have a valid primary key value');
        }

        // Store the user ID
        $this->session->put($this->sessionKey, $userId);

        // Regenerate session ID for security
        $this->session->regenerate();

        // Store user in memory
        $this->user = $user;
    }

    /**
     * Log out the current user
     */
    public function logout(): void
    {
        $this->ensureAuthEnabled();

        $this->session->start();
        $this->session->forget($this->sessionKey);
        $this->session->regenerate();
        $this->user = null;
    }

    /**
     * Get the currently authenticated user
     * Returns null if auth is not enabled or user is not authenticated
     */
    public function user(): ?User
    {
        // Don't throw exception - just return null if auth is disabled
        if (!$this->isAuthEnabled()) {
            return null;
        }

        if ($this->user !== null) {
            return $this->user;
        }

        $this->session->start();
        $userId = $this->session->get($this->sessionKey);

        if (!$userId) {
            return null;
        }

        $this->user = $this->getUserById($userId);

        if (!$this->user) {
            // Clear invalid session
            $this->session->forget($this->sessionKey);
        }

        return $this->user;
    }

    /**
     * Check if user is authenticated
     * Returns false if auth is not enabled
     */
    public function check(): bool
    {
        // Don't throw exception - just return false if auth is disabled
        if (!$this->isAuthEnabled()) {
            return false;
        }

        return $this->user() !== null;
    }

    /**
     * Check if user is a guest (not authenticated)
     * Returns true if auth is not enabled
     */
    public function guest(): bool
    {
        // Don't throw exception - just return true if auth is disabled
        if (!$this->isAuthEnabled()) {
            return true;
        }

        return !$this->check();
    }

    /**
     * Get user ID
     * Returns null if auth is not enabled or user is not authenticated
     */
    public function id(): mixed
    {
        // Don't throw exception - just return null if auth is disabled
        if (!$this->isAuthEnabled()) {
            return null;
        }

        $user = $this->user();

        if (!$user) {
            return null;
        }

        return ($user instanceof Model) ? $user->getKey() : ($user->id ?? null);
    }

    /**
     * Get user dynamically by credentials
     */
    protected function getUserByCredentials(array $credentials): ?object
    {
        $model = $this->userEntity;
        $query = $model::query();

        foreach ($credentials as $field => $value) {
            $query->where($field, $value);
        }

        return $query->first();
    }

    /**
     * Get user by ID
     */
    protected function getUserById(mixed $id): ?object
    {
        $model = $this->userEntity;
        return $model::find($id);
    }
}