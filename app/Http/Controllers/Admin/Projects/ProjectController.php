<?php

namespace App\Http\Controllers\Admin\Projects;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProjectRequest;
use App\Repository\Services\Admin\Projects\ProjectService;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Support\Facades\Log;

class ProjectController extends Controller
{

    private $projectService;
    public function __construct(ProjectService $projectService)
    {
        $this->projectService = $projectService;
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

    public function addNewProject(ProjectRequest $request)
    {
        try {

            $insertedProjectInformation = $this->projectService->store($request->validated());
            return response()->json([
                "isExecuted" => $insertedProjectInformation['status'],
                "message" => $insertedProjectInformation['message'],
                "data" => $insertedProjectInformation['data']
            ], 200);
        } catch (Exception $ex) {
            Log::info("addNewProject function error: " . $ex->getMessage());
        }
    }

    public function getProjectById($id)
    {
        try {
            $response = $this->projectService->getProjectById($id);
            return response()->json([
                "isExecuted" => $response['status'],
                "message" => $response['message'],
                "data" => $response['data']
            ], 200);
        } catch (Exception $ex) {
            Log::info("getProjectById function error: " . $ex->getMessage());
        }
    }

    public function updateProject($id, ProjectRequest $request)
    {
        try {
            $response = $this->projectService->updateProject($id, $request->validated());
            return response()->json([
                "isExecuted" => $response['status'],
                "message" => $response['message'],
                "data" => $response['data']
            ], 200);
        } catch (Exception $ex) {
            Log::info("updateBlog function error: " . $ex->getMessage());
        }
    }
}
