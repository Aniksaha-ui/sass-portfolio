<?php

namespace App\Http\Controllers\User\Projects;

use App\Http\Controllers\Controller;
use App\Repository\Services\Admin\Users\Project\ProjectService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProjectController extends Controller
{
     private $projectService;
    public function __construct(ProjectService $experienceService)
    {
        $this->projectService = $experienceService;
    }

    public function getAllProjects(Request $request)
    {
        try {
            $page = $request->query('page') ?? 1;
            $search = $request->query('search') ?? '';
            $perPage = $request->query('perPage') ?? 10;
            $response = $this->projectService->getProjects($perPage, $page, $search);

            return response()->json([
                "isExecuted" => $response['status'],
                "message" => $response['message'],
                "data" => $response['data']
            ], 200);
        } catch (Exception $ex) {
            Log::info("getAllProjects function error: " . $ex->getMessage());
        }
    }


}
