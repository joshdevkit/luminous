<?php

use Core\Database\Capsule;

define('BASE_PATH', dirname(__DIR__));


require BASE_PATH . '/vendor/autoload.php';

/*
|--------------------------------------------------------------------------
| Create The Application
|--------------------------------------------------------------------------
|
| The first thing we will do is create a new application instance
| which serves as the "glue" for all the components of the framework,
| and is the IoC container for the system binding all of the various parts.
|
*/

$app = new Core\Framework\Application(BASE_PATH);
/*
|--------------------------------------------------------------------------
| Configure Middleware
|--------------------------------------------------------------------------
|
| Define global middleware that will be executed on every request.
| Middleware is executed in the order they are added.
|
*/

/*
|--------------------------------------------------------------------------
| Enable Authentication (Required)
|--------------------------------------------------------------------------
|
| This will register the AuthServiceProvider and make Auth features available.
|
*/
$app->useAuthentication();

$app->withMiddleware([
    /**
     * Start session and verify CSRF tokens
     */
     Core\Http\Middlewares\VerifyCsrfToken::class,
    /**
     * MAINTENANCE MODE - KEEP INJECTED
     * ================================================================
     * for production maintenance - set to true in env and false for development
     * ================================================================
     */
    Core\Http\Middlewares\CheckMaintenanceMode::class, 
]);

/*
|--------------------------------------------------------------------------
| Return The Application
|--------------------------------------------------------------------------
|
| This script returns the application instance. The instance is given to
| the calling script so we can separate the building of the instances
| from the actual running of the application and sending responses.
|
*/

return $app;