<?php

namespace App\Controllers\Api;

use Core\Routing\Attributes\ApiRoute;
use Core\Routing\Controller;

#[ApiRoute(prefix: 'api/test')]
class TestController extends Controller
{
    public function index()
    {
        return response()->json([
            'message' => 'If you see this message means the api is working',
            'timestamp' => now()->format('Y-m-d H:i:s'),
        ]);
    }
}
