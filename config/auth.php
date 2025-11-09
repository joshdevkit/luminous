<?php

// ============================================
// 7. Auth Configuration (config/auth.php)
// ============================================

return [
    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    |
    | The model used for authentication
    |
    */
    'model' => App\Entities\User::class,

    /*
    |--------------------------------------------------------------------------
    | Redirect URLs
    |--------------------------------------------------------------------------
    | Configurable redirect *
    | Where to redirect users after login/logout
    | Use to automatically redirect authenticated user to home key => '/dashboard handled by middleware
    |
    */
    'redirect' => [
        'login' => '/login',
        'logout' => '/',
        'home' => '/',
    ],
];