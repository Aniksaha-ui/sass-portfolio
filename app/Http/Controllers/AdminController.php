<?php

namespace App\Http\Controllers;

use App\Repository\Services\Dashboard\DashboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{

    private $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    public function dashboard()
    {
        $users = $this->dashboardService->index();
        return response()->json([
            "data" => $users,
            "message" => "success"
        ], 200);
    }
}
