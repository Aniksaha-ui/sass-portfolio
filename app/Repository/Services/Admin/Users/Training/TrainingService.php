<?php

namespace App\Repository\Services\Admin\Users\Training;

use App\Repository\Services\Common\CommonService;
use Exception;
use Illuminate\Support\Facades\Log;
use DB;


class TrainingService
{

    private $commonService;
    public function __construct(CommonService $commonService)
    {
        $this->commonService = $commonService;
    }


    public function getTrainingsData($page, $perPage, $search)
    {
        try {
            $perPage = isset($perPage) ? intval($perPage) : 10;
            $user = DB::table('trainings')
                ->where(function ($query) use ($search) {
                    $query->where('title', 'like', '%' . $search . '%')
                        ->orWhere('provider', 'like', '%' . $search . '%')
                        ->orWhere('description', 'like', '%' . $search . '%')
                        ->orWhere('start_date', 'like', '%' . $search . '%')
                        ->orWhere('end_date', 'like', '%' . $search . '%')
                        ->orWhere('certificate_url', 'like', '%' . $search . '%');
                })
                ->paginate($perPage, ['id', 'title', 'provider', 'description', 'start_date', 'end_date', 'certificate_url', 'status'], 'page', $page);

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
