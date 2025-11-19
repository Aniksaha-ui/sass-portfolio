<?php

namespace App\Http\Controllers\Admin\Experience;

use App\Http\Controllers\Controller;
use App\Repository\Services\Admin\Experience\ExperienceService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminExperienceController extends Controller
{


     private $experienceService;
    public function __construct(ExperienceService $experienceService)
    {
        $this->experienceService = $experienceService;
    }

      public function getAllExperience(Request $request)
    {
        try {
            $page = $request->query('page') ?? 1;
            $search = $request->query('search') ?? '';
            $perPage = $request->query('perPage') ?? 10;
            $response = $this->experienceService->getExperience($perPage, $page, $search);

            return response()->json([
                "isExecuted" => $response['status'],
                "message" => $response['message'],
                "data" => $response['data']
            ], 200);
        } catch (Exception $ex) {
            Log::info("getAllExperience function error: " . $ex->getMessage());
        }
    }

    public function addNewExperience(Request $request)
    {
        try {

            $insertedExperienceInformation = $this->experienceService->store($request->all());
            return response()->json([
                "isExecuted" => $insertedExperienceInformation['status'],
                "message" => $insertedExperienceInformation['message'],
                "data" => $insertedExperienceInformation['data']
            ], 200);
        } catch (Exception $ex) {
            Log::info("addNewExperience function error: " . $ex->getMessage());
        }
    }

    public function getExperienceById($id)
    {
        try {
            $response = $this->experienceService->getExperienceById($id);
            return response()->json([
                "isExecuted" => $response['status'],
                "message" => $response['message'],
                "data" => $response['data']
            ], 200);
        } catch (Exception $ex) {
            Log::info("getExperienceById function error: " . $ex->getMessage());
        }
    }

    public function updateExperience($id, Request $request)
    {
        try {
            $response = $this->experienceService->updateExperience($id, $request->all());
            return response()->json([
                "isExecuted" => $response['status'],
                "message" => $response['message'],
                "data" => $response['data']
            ], 200);
        } catch (Exception $ex) {
            Log::info("updateExperience function error: " . $ex->getMessage());
        }
    }
}
