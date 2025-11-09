<?php

namespace App\Controllers;

use Core\Routing\Attributes\Get;
use Core\Routing\Controller;

class HomeController extends Controller
{
    #[Get("/")]
    public function index()
    {
        return view('welcome');
    }
}
