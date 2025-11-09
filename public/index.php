<?php

/**
 * ---------------------------------------------------------------
 * Public HTTP Entry Point
 * ---------------------------------------------------------------
 * Handles every web request, bootstraps the environment,
 * then passes control to the app kernel.
 */

$app = require __DIR__ . '/../bootstrap/app.php';
// Run the application
$app->run();
