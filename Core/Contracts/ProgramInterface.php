<?php

namespace Core\Contracts;

use Core\Contracts\ApplicationInterface;

interface ProgramInterface
{
    /**
     * Bootstrap the application.
     *
     * @return ApplicationInterface
     */
    public function bootstrap(): ApplicationInterface;

    /**
     * Run the application (handle the request and send the response).
     *
     * @return void
     */
    public function run(): void;

    /**
     * @return self
     */
    public function useAuthentication(): self;
}
