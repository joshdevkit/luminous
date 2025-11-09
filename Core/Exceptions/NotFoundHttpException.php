<?php

namespace Core\Exceptions;

class NotFoundHttpException extends \RuntimeException
{
    protected string $method;
    protected string $path;

    public function __construct(string $method, string $path, string $message = '')
    {
        $this->method = $method;
        $this->path = $path;
        
        if (empty($message)) {
            $message = "Route not found: {$method} {$path}";
        }
        
        parent::__construct($message, 404);
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getPath(): string
    {
        return $this->path;
    }
}