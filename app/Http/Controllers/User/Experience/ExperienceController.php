<?php

namespace App\Http\Controllers\User\Experience;

use App\Http\Controllers\Controller;
use App\Repository\Services\Admin\Users\Experience\ExperienceService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ExperienceController extends Controller
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
}
