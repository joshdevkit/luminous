<?php

namespace Core\Exceptions;

use Exception;

class AuthenticationDisabledException extends Exception
{
    public function __construct(string $message = 'Authentication is disabled for this application.')
    {
        parent::__construct($message);
    }
}
