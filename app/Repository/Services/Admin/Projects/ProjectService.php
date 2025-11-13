<?php

namespace App\Repository\Services\Admin\Projects;

use App\Helpers\admin\FileManageHelper;
use App\Repository\Services\Common\CommonService;
use Exception;
use Illuminate\Support\Facades\Log;
use DB;


class ProjectService
{

    private $commonService;
    public function __construct(CommonService $commonService)
    {
        $this->commonService = $commonService;
    }

    public function store($data)
    {
        try {
            if (request()->hasFile('image')) {
                $documentLink = FileManageHelper::uploadFile('projects', $data['image']);
                $data['image'] = $documentLink;
            } else {
                $request['image'] = 'images/trips/default.png';
            }
            

            $projectId = DB::table('projects')->insertGetId($data);
            if ($projectId) {
                return [
                    "status" => true,
                    "message" => "Projects created successfully",
                    "data" => $projectId
                ];
            } else {
                return [
                    "status" => false,
                    "message" => "Projects not created",
                    "data" => []
                ];
            }
        } catch (Exception $e) {
            Log::error("ProjectService store function error: " . $e->getMessage());
            return $this->commonService->internalServerErrorResponse(false, "Internal server error", []);
        }
    }

    public function getProjects($perPage, $page, $search)
    {
        try {
            $projects = DB::table('projects')
                ->where(function ($query) use ($search) {
                    $query->where('project_name', 'like', '%' . $search . '%')
                        ->orWhere('website_link', 'like', '%' . $search . '%')
                        ->orWhere('frontend_tech', 'like', '%' . $search . '%')
                        ->orWhere('backend_tech', 'like', '%' . $search . '%')
                        ->orWhere('database', 'like', '%' . $search . '%')
                        ->orWhere('github_link', 'like', '%' . $search . '%')
                        ->orWhere('developers_name', 'like', '%' . $search . '%');
                })
                ->paginate($perPage, ['*'], 'page', $page);

            if ($projects->count() > 0) {
                return [
                    "status" => true,
                    "message" => "Projects fetched successfully",
                    "data" => $projects
                ];
            } else {
                return [
                    "status" => true,
                    "message" => "No projects found",
                    "data" => []
                ];
            }
        } catch (Exception $ex) {
            Log::error("ProjectService getProjects function error: " . $ex->getMessage());
            return $this->commonService->internalServerErrorResponse(false, "Internal Server Error. Please Contact Admin", []);
        }
    }

    public function getProjectById($id)
    {
        try {
            $projects = DB::table('projects')->where('id', $id)->first();
            if ($projects) {
                return [
                    "status" => true,
                    "message" => "Projects fetched successfully",
                    "data" => $projects
                ];
            } else {
                return [
                    "status" => true,
                    "message" => "No project found",
                    "data" => []
                ];
            }
        } catch (Exception $ex) {
            Log::error("ProjectService getProjectById function error: " . $ex->getMessage());
            return $this->commonService->internalServerErrorResponse(false, "Internal Server Error. Please Contact Admin", []);
        }
    }


    public function updateProject($id, $data)
    {
        try {
            $projectInformationExist = DB::table('projects')->where('id', $id)->exists();
            if (!$projectInformationExist) {
                return [
                    "status" => true,
                    "message" => "Project not found",
                    "data" => []
                ];
            }

            if (request()->hasFile('image')) {
                $documentLink = FileManageHelper::uploadFile('users', $data['image']);
                $data['image'] = $documentLink;
            } else {
                $request['image'] = 'images/trips/default.png';
            }
            $project = DB::table('projects')->where('id', $id)->update($data);
            if ($project) {
                return [
                    "status" => true,
                    "message" => "Project Information updated successfully",
                    "data" => $project
                ];
            } else {
                return [
                    "status" => true,
                    "message" => "Project Information not updated successfully",
                    "data" => []
                ];
            }
        } catch (Exception $ex) {
            Log::error("ProjectService updateProject function error: " . $ex->getMessage());
            return $this->commonService->internalServerErrorResponse(false, $ex->getMessage(), []);
        }
    }
}
