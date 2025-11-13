<?php

namespace App\Http\Controllers\User\Training;

use App\Http\Controllers\Controller;
use App\Repository\Services\Admin\Users\Training\TrainingService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TrainingController extends Controller
{
    private $trainingService;
    public function __construct(TrainingService $trainingService)
    {
        $this->trainingService = $trainingService;
    }

    public function getAllTraining(Request $request)
    {
        try {
            $page = $request->query('page') ?? 1;
            $search = $request->query('search') ?? '';
            $perPage = $request->query('perPage') ?? 10;
            $response = $this->trainingService->getTrainingsData($page, $perPage, $search);

            return response()->json([
                "isExecuted" => $response['status'],
                "message" => $response['message'],
                "data" => $response['data']
            ], 200);
        } catch (Exception $ex) {
            Log::info("getAllTestimonials function error: " . $ex->getMessage());
        }
    }
}
