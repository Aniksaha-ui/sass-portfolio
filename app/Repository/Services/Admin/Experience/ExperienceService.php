<?php

namespace App\Repository\Services\Admin\Experience;

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

    public function store($data)
    {
        try {
              if (request()->hasFile('image_url')) {
                $documentLink = FileManageHelper::uploadFile('experience', $data['image_url']);
                $data['image_url'] = $documentLink;
            } else {
                $data['image_url'] = 'images/trips/default.png';
            }

        

            $userId = DB::table('expriences')->insertGetId($data);
            if ($userId) {
                return [
                    "status" => true,
                    "message" => "Experiences created successfully",
                    "data" => $userId
                ];
            } else {
                return [
                    "status" => false,
                    "message" => "Experiences not created",
                    "data" => []
                ];
            }
        } catch (Exception $e) {
            Log::error("ExperienceService - store function error: " . $e->getMessage());
            return $this->commonService->internalServerErrorResponse(false, "Internal server error", []);
        }
    }

    public function getExperience($perPage, $page, $search)
    {
        try {
            $user = DB::table('expriences')
                ->where(function ($query) use ($search) {
                    $query->where('job_title', 'like', '%' . $search . '%')
                        ->orWhere('company', 'like', '%' . $search . '%')
                        ->orWhere('location', 'like', '%' . $search . '%');
                })
                ->orderBy('id', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);

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
            Log::error("experienceservice  - getExperience function error: " . $ex->getMessage());
            return $this->commonService->internalServerErrorResponse(false, "Internal Server Error. Please Contact Admin", []);
        }
    }

    public function getExperienceById($id)
    {
        try {

            $user = DB::table('expriences')->where('id', $id)->first();
            if ($user) {
                return [
                    "status" => true,
                    "message" => "Experiences fetched successfully",
                    "data" => $user
                ];
            } else {
                return [
                    "status" => true,
                    "message" => "No experiences found",
                    "data" => []
                ];
            }
        } catch (Exception $ex) {
            Log::error("getExperienceById function error: " . $ex->getMessage());
            return $this->commonService->internalServerErrorResponse(false, "Internal Server Error. Please Contact Admin", []);
        }
    }


    public function updateExperience($id, $data)
    {
        try {
            $userInformationExist = DB::table('expriences')->where('id', $id)->exists();
            if (!$userInformationExist) {
                return [
                    "status" => true,
                    "message" => "Experience not found",
                    "data" => []
                ];
            }

            $experiences = DB::table('expriences')->where('id', $id)->update($data);
            if ($experiences) {
                return [
                    "status" => true,
                    "message" => "Experience Information updated successfully",
                    "data" => $experiences
                ];
            } else {
                return [
                    "status" => true,
                    "message" => "Experience Information not updated successfully",
                    "data" => []
                ];
            }
        } catch (Exception $ex) {
            Log::error("updateExperience function error: " . $ex->getMessage());
            return $this->commonService->internalServerErrorResponse(false, $ex->getMessage(), []);
        }
    }
}
