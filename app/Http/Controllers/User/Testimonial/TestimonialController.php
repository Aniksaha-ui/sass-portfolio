<?php

namespace App\Http\Controllers\User\Testimonial;

use App\Http\Controllers\Controller;
use App\Repository\Services\Admin\Users\Testimonial\TestimonialsService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TestimonialController extends Controller
{
    //getAllTestimonials


    private $testimonialsService;
    public function __construct(TestimonialsService $testimonialsService)
    {
        $this->testimonialsService = $testimonialsService;
    }

    public function getAllTestimonials(Request $request)
    {
        try {
            $page = $request->query('page') ?? 1;
            $search = $request->query('search') ?? '';
            $perPage = $request->query('perPage') ?? 10;
            $response = $this->testimonialsService->getTestimonials($perPage, $page, $search);

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
