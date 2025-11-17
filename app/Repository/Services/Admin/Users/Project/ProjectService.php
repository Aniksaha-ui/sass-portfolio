<?php

namespace App\Repository\Services\Admin\Users\Project;

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


    public function getProjects($perPage, $page, $search)
    {
        try {

            $perPage = isset($perPage) ? intval($perPage) : 10;
            $user = DB::table('projects')
                ->where(function ($query) use ($search) {
                    $query->where('project_name', 'like', '%' . $search . '%')
                        ->orWhere('description', 'like', '%' . $search . '%')
                        ->orWhere('frontend_tech', 'like', '%' . $search . '%')
                        ->orWhere('backend_tech', 'like', '%' . $search . '%')
                        ->orWhere('github_link', 'like', '%' . $search . '%')
                        ->orWhere('developers_name', 'like', '%' . $search . '%');
                })
                ->paginate($perPage, ['*'], 'page', $page);

            if ($user->count() > 0) {
                return [
                    "status" => true,
                    "message" => "project fetched successfully",
                    "data" => $user
                ];
            } else {
                return [
                    "status" => true,
                    "message" => "No project found",
                    "data" => []
                ];
            }
        } catch (Exception $ex) {
            Log::error("getProjects function error: " . $ex->getMessage());
            return $this->commonService->internalServerErrorResponse(false, "Internal Server Error. Please Contact Admin", []);
        }
    }
}
