<?php

namespace Core\Routing\Traits;

trait RegeneratesSession
{
    /**
     * Regenerate session ID and CSRF token after authentication state change
     * 
     * This method should be called after:
     * - User registration
     * - User login
     * - User logout
     * - Password change
     * - Email change
     * - Permission/role changes
     * - Any other authentication state change
     *
     * @return void
     */
    protected function regenerateSessionAfterAuth(): void
    {
        $session = session();
        
        /**
         * Step 1: Regenerate session ID
         * Prevents session fixation attacks by invalidating the old session ID
         * The old session file is deleted (true parameter)
         */
        $session->regenerate(true);
        
        /**
         * Step 2: Regenerate CSRF token
         * Invalidates any CSRF tokens that may have been captured before
         * the authentication state change
         */
        $session->regenerateToken();
        
        /**
         * Step 3: Reset periodic regeneration timer
         * Prevents the periodic regeneration from triggering immediately
         * after an authentication state change
         */
        $session->put('_last_regeneration', time());
    }

    /**
     * Completely destroy the session and start fresh
     * 
     * Use this for complete logout scenarios where you want to clear
     * all session data, not just regenerate the session ID
     *
     * @param bool $startNew Whether to start a new session after destruction
     * @return void
     */
    protected function destroyAndRegenerateSession(bool $startNew = true): void
    {
        $session = session();
        
        /**
         * Completely destroy the current session
         * This removes all session data and invalidates the session ID
         */
        $session->destroy();
        
        if ($startNew) {
            /**
             * Start a new session for flash messages and CSRF protection
             */
            $session->start();
            $session->regenerateToken();
            $session->put('_last_regeneration', time());
        }
    }

    /**
     * Regenerate session for privilege escalation
     * 
     * Use this when a user's privileges increase (e.g., becoming admin)
     * This preserves session data but creates a new session ID
     *
     * @return void
     */
    protected function regenerateSessionForPrivilegeChange(): void
    {
        $session = session();
        
        /**
         * Regenerate with preservation of session data
         * This is important for privilege escalation scenarios
         */
        $session->regenerate(true);
        $session->regenerateToken();
        $session->put('_last_regeneration', time());
        
        /**
         * Log the privilege change for security auditing
         */
        // if (function_exists('logger')) {
        //     logger()->info('Session regenerated due to privilege change', [
        //         'user_id' => auth()->id(),
        //         'session_id' => $session->getId(),
        //     ]);
        // }
    }
}