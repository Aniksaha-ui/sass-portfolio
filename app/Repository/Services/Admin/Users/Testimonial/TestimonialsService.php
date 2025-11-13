<?php

namespace App\Repository\Services\Admin\Users\Testimonial;

use App\Repository\Services\Common\CommonService;
use Exception;
use Illuminate\Support\Facades\Log;
use DB;


class TestimonialsService
{

    private $commonService;
    public function __construct(CommonService $commonService)
    {
        $this->commonService = $commonService;
    }


    public function getTestimonials($perPage, $page, $search)
    {
        try {

            $perPage = isset($perPage) ? intval($perPage) : 10;
            $user = DB::table('testimonials')
                ->where(function ($query) use ($search) {
                    $query->where('name', 'like', '%' . $search . '%')
                        ->orWhere('designation', 'like', '%' . $search . '%');
                })
                ->paginate($perPage, ['id', 'name', 'designation', 'rating', 'image_url', 'status'], 'page', $page);

            if ($user->count() > 0) {
                return [
                    "status" => true,
                    "message" => "Testimonials fetched successfully",
                    "data" => $user
                ];
            } else {
                return [
                    "status" => true,
                    "message" => "No testimonials found",
                    "data" => []
                ];
            }
        } catch (Exception $ex) {
            Log::error("getTestimonials function error: " . $ex->getMessage());
            return $this->commonService->internalServerErrorResponse(false, "Internal Server Error. Please Contact Admin", []);
        }
    }
}
