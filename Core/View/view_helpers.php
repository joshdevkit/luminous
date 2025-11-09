<?php

/**
 * Helper Functions for Views
 * 
 * Add these to your app/Helpers/view_helpers.php or similar
 */

if (!function_exists('e')) {
    /**
     * Escape HTML entities in a string (null-safe)
     * 
     * @param mixed $value
     * @return string
     */
    function e(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('old')) {
    function old(string $key, mixed $default = ''): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        // Try multiple locations
        $oldInput = $_SESSION['flash']['old_input'] 
            ?? $_SESSION['old_input'] 
            ?? $_POST 
            ?? [];
        
        $value = $oldInput[$key] ?? $default;
        
        return (string) $value;
    }
}

if (!function_exists('csrf_token')) {
    /**
     * Get the CSRF token value
     * 
     * @return string
     */
    function csrf_token(): string
    {
        if (function_exists('app') && app()->has('session')) {
            return app()->get('session')->token() ?? '';
        }

        // Fallback to raw session
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        if (!isset($_SESSION['_token'])) {
            $_SESSION['_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['_token'];
    }
}

if (!function_exists('has_error')) {
    /**
     * Check if a field has validation error
     * 
     * @param string $field
     * @return bool
     */
    function has_error(string $field): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $errors = $_SESSION['errors'] ?? null;
        
        if ($errors instanceof \Core\Support\ErrorBag) {
            return $errors->has($field);
        }

        return false;
    }
}

if (!function_exists('error_class')) {
    /**
     * Return error class if field has error
     * 
     * @param string $field
     * @param string $errorClass Default: 'is-invalid'
     * @return string
     */
    function error_class(string $field, string $errorClass = 'is-invalid'): string
    {
        return has_error($field) ? $errorClass : '';
    }
}