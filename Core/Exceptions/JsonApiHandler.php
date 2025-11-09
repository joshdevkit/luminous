<?php

namespace Core\Exceptions;

use Throwable;
use Whoops\Handler\JsonResponseHandler;
use Whoops\Handler\Handler;

class JsonApiHandler extends JsonResponseHandler
{
    public function handle(): ?int
    {
        $exception = $this->getException(); // Retrieve the exception from the handler context
        $message = $exception->getMessage();
        // Detect SQL "Unknown column" error and simplify
        if (preg_match('/Unknown column \'([^\']+)\'/', $message, $matches)) {
            $message = "Column not found: {$matches[1]}";
        }
        // Build JSON response manually similar to Whoops\JsonResponseHandler
        $response = [
            'success' => false,
            'errors' => [
                [
                    'type'    => get_class($exception),
                    'message' => $exception->getMessage(),
                    'message' => $message,
                    'code'    => $exception->getCode(),
                    'file'    => $exception->getFile(),
                    'line'    => $exception->getLine()
                ],
            ],
        ];

        // Output JSON
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return Handler::QUIT;
    }
}
