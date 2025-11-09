<?php

namespace Core\Exceptions;

use Core\Contracts\Http\ResponseInterface;
use Core\Http\Response;

class HttpErrorRenderer
{
    /**
     * Render an error page (404, 500, etc.)
     */
    public static function render(int $status, string $message = '', $uri = null, $details = null): ResponseInterface
    {
        $html = '';
        
        switch ($status) {
            case 404:
                $html = self::renderNotFoundPage($uri ?? '/');
                break;
            case 403:
                $html = self::renderForbiddenPage($message);
                break;
            case 500:
                $html = self::renderServerErrorPage($message, $details);
                break;
            case 503:
                $html = self::renderServiceUnavailablePage($message, $details);
                break;
            default:
                $html = self::renderGenericErrorPage($status, $message, $details);
                break;
        }

        return new Response($html, $status);
    }

    /**
     * Render a security configuration error page.
     */
    public static function renderSecurityError(string $message, array $details = []): ResponseInterface
    {
        $html = self::renderSecurityConfigurationPage($message, $details);
        return new Response($html, 503);
    }

    /**
     * Render the 404 "Not Found" page
     */
    protected static function renderNotFoundPage(string $uri): string
    {
        $uri = htmlspecialchars($uri, ENT_QUOTES, 'UTF-8');
        $styles = self::getSharedStyles();
        
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 | Page Not Found</title>
    <style>
        {$styles}
    </style>
</head>
<body>
    <div class="container">
        <div class="error-icon">🔍</div>
        <div class="code">404</div>
        <div class="title">Page Not Found</div>
        <div class="message">Sorry, the page you are looking for could not be found.</div>
        <div class="details">
            <strong>Requested URI:</strong> {$uri}
        </div>
        <div class="actions">
            <a href="/" class="btn">Go Home</a>
            <a href="javascript:history.back()" class="btn btn-secondary">Go Back</a>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Render a 403 Forbidden page
     */
    protected static function renderForbiddenPage(string $message): string
    {
        if (empty($message)) {
            $message = 'You do not have permission to access this resource.';
        }
        $message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        $styles = self::getSharedStyles();
        
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 | Forbidden</title>
    <style>
        {$styles}
    </style>
</head>
<body>
    <div class="container">
        <div class="error-icon">🚫</div>
        <div class="code">403</div>
        <div class="title">Access Forbidden</div>
        <div class="message">{$message}</div>
        <div class="actions">
            <a href="/" class="btn">Go Home</a>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Render a 500 Internal Server Error page
     */
    protected static function renderServerErrorPage(string $message, $details = null): string
    {
        if (empty($message)) {
            $message = 'An unexpected error occurred.';
        }
        $message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        $detailsHtml = self::renderDetailsSection($details);
        $styles = self::getSharedStyles();
        
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>500 | Internal Server Error</title>
    <style>
        {$styles}
    </style>
</head>
<body>
    <div class="container">
        <div class="error-icon">⚠️</div>
        <div class="code">500</div>
        <div class="title">Internal Server Error</div>
        <div class="message">{$message}</div>
        {$detailsHtml}
        <div class="actions">
            <a href="/" class="btn">Go Home</a>
            <a href="javascript:location.reload()" class="btn btn-secondary">Retry</a>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Render a security configuration error page
     */
    protected static function renderSecurityConfigurationPage(string $message, array $details = []): string
    {
        $message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        $env = env('APP_ENV');
        $isDevelopment = $env !== 'production';
        
        $detailsHtml = '';
        if ($isDevelopment && !empty($details)) {
            $detailsHtml = '<div class="error-details">';
            $detailsHtml .= '<h3>Configuration Issue Details</h3>';
            $detailsHtml .= '<div class="detail-content">';
            
            if (isset($details['missing_middleware'])) {
                $middleware = htmlspecialchars($details['missing_middleware'], ENT_QUOTES, 'UTF-8');
                $detailsHtml .= "<p><strong>Missing Middleware:</strong> <code>{$middleware}</code></p>";
            }
            
            if (isset($details['solution'])) {
                $solution = htmlspecialchars($details['solution'], ENT_QUOTES, 'UTF-8');
                $detailsHtml .= "<p><strong>Solution:</strong> {$solution}</p>";
            }
            
            if (isset($details['documentation'])) {
                $doc = htmlspecialchars($details['documentation'], ENT_QUOTES, 'UTF-8');
                $detailsHtml .= "<p><strong>Documentation:</strong> <a href=\"{$doc}\" target=\"_blank\">{$doc}</a></p>";
            }
            
            if (isset($details['severity'])) {
                $severity = htmlspecialchars($details['severity'], ENT_QUOTES, 'UTF-8');
                $detailsHtml .= "<p><strong>Severity:</strong> {$severity}</p>";
            }
            
            if (isset($details['impact'])) {
                $impact = htmlspecialchars($details['impact'], ENT_QUOTES, 'UTF-8');
                $detailsHtml .= "<p><strong>Impact:</strong> {$impact}</p>";
            }
            
            $detailsHtml .= '</div></div>';
        }
        
        $environmentNote = $isDevelopment 
            ? '<div class="env-notice">⚙️ Development Mode - Showing detailed error information</div>'
            : '';
        
        $styles = self::getSharedStyles();
        
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>503 | Configuration Error</title>
    <style>
        {$styles}
        .env-notice {
            background: rgba(255, 193, 7, 0.1);
            border: 1px solid rgba(255, 193, 7, 0.3);
            color: #ffc107;
            padding: 0.75rem 1rem;
            border-radius: 6px;
            margin-top: 1.5rem;
            font-size: 0.9rem;
        }
        .error-details {
            background: rgba(220, 53, 69, 0.05);
            border: 1px solid rgba(220, 53, 69, 0.2);
            border-radius: 8px;
            padding: 1.5rem;
            margin-top: 1.5rem;
            text-align: left;
        }
        .error-details h3 {
            color: #dc3545;
            margin-top: 0;
            margin-bottom: 1rem;
            font-size: 1.1rem;
        }
        .detail-content {
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            line-height: 1.6;
        }
        .detail-content p {
            margin: 0.5rem 0;
        }
        .detail-content code {
            background: rgba(0, 0, 0, 0.3);
            padding: 0.2rem 0.5rem;
            border-radius: 3px;
            color: #e83e8c;
            font-size: 0.85rem;
        }
        .detail-content a {
            color: #17a2b8;
            text-decoration: none;
        }
        .detail-content a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="error-icon">🔒</div>
        <div class="code">503</div>
        <div class="title">Security Configuration Error</div>
        <div class="message">{$message}</div>
        {$detailsHtml}
        {$environmentNote}
        <div class="actions">
            <a href="javascript:location.reload()" class="btn">Retry</a>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Render a 503 Service Unavailable page
     */
    protected static function renderServiceUnavailablePage(string $message, $details = null): string
    {
        if (empty($message)) {
            $message = 'The service is temporarily unavailable.';
        }
        $message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        $detailsHtml = self::renderDetailsSection($details);
        $styles = self::getSharedStyles();
        
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>503 | Service Unavailable</title>
    <style>
        {$styles}
    </style>
</head>
<body>
    <div class="container">
        <div class="error-icon">🔧</div>
        <div class="code">503</div>
        <div class="title">Service Unavailable</div>
        <div class="message">{$message}</div>
        {$detailsHtml}
        <div class="actions">
            <a href="javascript:location.reload()" class="btn">Retry</a>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Fallback for generic error pages
     */
    protected static function renderGenericErrorPage(int $status, string $message, $details = null): string
    {
        $title = self::getStatusTitle($status);
        if (empty($message)) {
            $message = 'An error occurred while processing your request.';
        }
        $message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        $detailsHtml = self::renderDetailsSection($details);
        $styles = self::getSharedStyles();
        
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$status} | {$title}</title>
    <style>
        {$styles}
    </style>
</head>
<body>
    <div class="container">
        <div class="error-icon">❌</div>
        <div class="code">{$status}</div>
        <div class="title">{$title}</div>
        <div class="message">{$message}</div>
        {$detailsHtml}
        <div class="actions">
            <a href="/" class="btn">Go Home</a>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Render details section if provided
     */
    protected static function renderDetailsSection($details): string
    {
        if (empty($details) || !is_array($details)) {
            return '';
        }
        
        $env = getenv('APP_ENV');
        if ($env === false) {
            $env = $_ENV['APP_ENV'] ?? 'production';
        }
        
        if ($env === 'production') {
            return '';
        }

        $detailsList = '';
        foreach ($details as $key => $value) {
            $key = htmlspecialchars(ucfirst(str_replace('_', ' ', $key)), ENT_QUOTES, 'UTF-8');
            if (is_array($value)) {
                $value = json_encode($value);
            } else {
                $value = (string)$value;
            }
            $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            $detailsList .= "<div class=\"detail-item\"><strong>{$key}:</strong> {$value}</div>";
        }

        return <<<HTML
<div class="details">
    {$detailsList}
</div>
HTML;
    }

    /**
     * Get status title from status code
     */
    protected static function getStatusTitle(int $status): string
    {
        $titles = [
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            408 => 'Request Timeout',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout',
        ];
        
        return $titles[$status] ?? 'Error';
    }

    /**
     * Get shared CSS styles for all error pages
     */
    protected static function getSharedStyles(): string
    {
        return <<<CSS
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

html, body {
    height: 100%;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    background: linear-gradient(135deg, #0f1419 0%, #1a1f2e 100%);
    color: #e1e4e8;
    display: flex;
    align-items: center;
    justify-content: center;
    line-height: 1.6;
}

.container {
    text-align: center;
    padding: 2rem;
    /* max-width: 600px; */
    animation: fadeIn 0.5s ease-in;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.error-icon {
    font-size: 4rem;
    margin-bottom: 1rem;
    animation: bounce 1s ease-in-out infinite;
}

@keyframes bounce {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-10px); }
}

.code {
    font-size: 8rem;
    font-weight: 700;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    line-height: 1;
    margin-bottom: 1rem;
}

.title {
    font-size: 2rem;
    font-weight: 600;
    color: #f0f3f6;
    margin-bottom: 1rem;
}

.message {
    font-size: 1.1rem;
    color: #8b949e;
    margin-bottom: 1.5rem;
    line-height: 1.5;
}

.details {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 8px;
    padding: 1rem;
    margin: 1.5rem 0;
    font-size: 0.9rem;
    color: #c9d1d9;
    text-align: left;
}

.detail-item {
    padding: 0.5rem 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

.detail-item:last-child {
    border-bottom: none;
}

.detail-item strong {
    color: #58a6ff;
}

.actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
    margin-top: 2rem;
    flex-wrap: wrap;
}

.btn {
    display: inline-block;
    padding: 0.75rem 1.5rem;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    text-decoration: none;
    border-radius: 6px;
    font-weight: 500;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
}

.btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
}

.btn-secondary {
    background: rgba(255, 255, 255, 0.1);
    box-shadow: none;
}

.btn-secondary:hover {
    background: rgba(255, 255, 255, 0.15);
    box-shadow: none;
}

@media (max-width: 768px) {
    .code {
        font-size: 5rem;
    }
    
    .title {
        font-size: 1.5rem;
    }
    
    .message {
        font-size: 1rem;
    }
    
    .actions {
        flex-direction: column;
    }
    
    .btn {
        width: 100%;
    }
}
CSS;
    }
}