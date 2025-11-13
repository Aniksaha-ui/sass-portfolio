<?php

namespace App\Repository\Services\Admin\Users\Experience;

use App\Helpers\admin\FileManageHelper;
use App\Repository\Services\Common\CommonService;
use Exception;
use Illuminate\Support\Facades\Log;
use DB;


class ExperienceService
{

    private $commonService;
    public function __construct(CommonService $commonService)
    {
        $this->commonService = $commonService;
    }


    public function getExperience($perPage, $page, $search)
    {
        try {

            $perPage = isset($perPage) ? intval($perPage) : 10;
            $user = DB::table('expriences')
                ->where(function ($query) use ($search) {
                    $query->where('job_title', 'like', '%' . $search . '%')
                        ->orWhere('company', 'like', '%' . $search . '%')
                        ->orWhere('location', 'like', '%' . $search . '%')
                        ->orWhere('description', 'like', '%' . $search . '%')
                        ->orWhere('start_date', 'like', '%' . $search . '%')
                        ->orWhere('end_date', 'like', '%' . $search . '%');
                })
                ->paginate($perPage, ['id', 'job_title', 'company', 'location', 'description', 'start_date', 'end_date', 'image_url', 'status', 'is_current'], 'page', $page);

            if ($user->count() > 0) {
                return [
                    "status" => true,
                    "message" => "Experience fetched successfully",
                    "data" => $user
                ];
            } else {
                return [
                    "status" => true,
                    "message" => "No experience found",
                    "data" => []
                ];
            }
        } catch (Exception $ex) {
            Log::error("getExperience function error: " . $ex->getMessage());
            return $this->commonService->internalServerErrorResponse(false, "Internal Server Error. Please Contact Admin", []);
        }
    }
}
