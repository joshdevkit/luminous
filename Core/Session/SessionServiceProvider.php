<?php

namespace Core\Session;

use Core\Contracts\Session\SessionInterface;
use Core\Providers\ServiceProvider;

class SessionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SessionInterface::class, function ($c) {
            $config = $c->get('config');
            $sessionConfig = $config->get('session', []);

            return new Session($sessionConfig);
        });

        $this->app->singleton('session', function ($c) {
            return $c->get(SessionInterface::class);
        });
    }

    public function boot(): void
    {
        // Skip completely if running CLI
        if (php_sapi_name() === 'cli') {
            return;
        }

        /** @var Session $session */
        $session = $this->app->get('session');

        $session->start();

        if (!$session->has('_token')) {
            $session->put('_token', bin2hex(random_bytes(32)));
        }

        $this->regenerateSessionPeriodically($session);
    }


    /**
     * Regenerate session ID periodically to prevent session fixation
     *
     * @param SessionInterface $session
     * @return void
     */
    protected function regenerateSessionPeriodically(SessionInterface $session): void
    {
        $lastRegeneration = $session->get('_last_regeneration', 0);
        $regenerationInterval = 1800; // 30 minutes

        if (time() - $lastRegeneration > $regenerationInterval) {
            $session->regenerate(true);
            $session->put('_last_regeneration', time());
        }
    }
}
