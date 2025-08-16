<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class BlogController extends Controller
{
     public function __construct(RouteService $routeService)
    {
        $this->routeService = $routeService;
    }
}
