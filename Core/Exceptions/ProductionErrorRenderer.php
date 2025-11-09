<?php

namespace Core\Exceptions;

class ProductionErrorRenderer
{
    /**
     * Render the production "Internal Server Error" page.
     */
    public static function render(): void
    {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');

        echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>500 | Internal Server Error</title>
    <style>
        html, body {
            height: 100%;
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: #131422ff;
            color: #7e8285ff;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
        }
        .code {
            font-size: 8rem;
            font-weight: 700;
            color: #6c757d;
        }
        .message {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }
        .sub {
            color: #999;
            font-size: 1rem;
        }
    </style>
</head>
<body>
    <div class="code">500</div>
    <div class="message">Internal Server Error</div>
    <div class="sub">Please try again later.</div>
</body>
</html>
HTML;
    }
}
