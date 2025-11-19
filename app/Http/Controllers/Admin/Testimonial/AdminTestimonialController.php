<?php

namespace App\Http\Controllers\Admin\Testimonial;

use App\Http\Controllers\Controller;
use App\Repository\Services\Admin\Experience\ExperienceService;
use App\Repository\Services\Admin\Testimonial\TestimonialService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminTestimonialController extends Controller
{


     private $testimonialService;
    public function __construct(TestimonialService $testimonialService)
    {
        $this->testimonialService = $testimonialService;
    }

      public function getAllTestimonials(Request $request)
    {
        try {
            $page = $request->query('page') ?? 1;
            $search = $request->query('search') ?? '';
            $perPage = $request->query('perPage') ?? 10;
            $response = $this->testimonialService->getTestimonial($perPage, $page, $search);

            return response()->json([
                "isExecuted" => $response['status'],
                "message" => $response['message'],
                "data" => $response['data']
            ], 200);
        } catch (Exception $ex) {
            Log::info("getAllExperience function error: " . $ex->getMessage());
        }
    }

    public function addNewTestimonial(Request $request)
    {
        try {

            $insertedTestimonialInformation = $this->testimonialService->store($request->all());
            return response()->json([
                "isExecuted" => $insertedTestimonialInformation['status'],
                "message" => $insertedTestimonialInformation['message'],
                "data" => $insertedTestimonialInformation['data']
            ], 200);
        } catch (Exception $ex) {
            Log::info("addNewTestimonial function error: " . $ex->getMessage());
        }
    }

    public function getTestimonialById($id)
    {
        try {
            $response = $this->testimonialService->getTestimonialById($id);
            return response()->json([
                "isExecuted" => $response['status'],
                "message" => $response['message'],
                "data" => $response['data']
            ], 200);
        } catch (Exception $ex) {
            Log::info("getExperienceById function error: " . $ex->getMessage());
        }
    }

    public function updateTestimonial($id, Request $request)
    {
        try {
            $response = $this->testimonialService->updateTestimonial($id, $request->all());
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
