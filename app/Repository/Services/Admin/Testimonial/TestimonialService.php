<?php

namespace App\Repository\Services\Admin\Testimonial;

use App\Helpers\admin\FileManageHelper;
use App\Repository\Services\Common\CommonService;
use Exception;
use Illuminate\Support\Facades\Log;
use DB;


class TestimonialService
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
                $documentLink = FileManageHelper::uploadFile('testimonial', $data['image_url']);
                $data['image_url'] = $documentLink;
            } else {
                $data['image_url'] = 'images/trips/default.png';
            }

        

            $testimonialId = DB::table('testimonials')->insertGetId($data);
            if ($testimonialId) {
                return [
                    "status" => true,
                    "message" => "Testimonials created successfully",
                    "data" => $testimonialId
                ];
            } else {
                return [
                    "status" => false,
                    "message" => "Experiences not created",
                    "data" => []
                ];
            }
        } catch (Exception $e) {
            Log::error("TestimonialService - store function error: " . $e->getMessage());
            return $this->commonService->internalServerErrorResponse(false, "Internal server error", []);
        }
    }

    public function getTestimonial($perPage, $page, $search)
    {
        try {
            $testimonial = DB::table('testimonials')
                ->where(function ($query) use ($search) {
                    $query->where('name', 'like', '%' . $search . '%')
                        ->orWhere('designation', 'like', '%' . $search . '%')
                        ->orWhere('feedback', 'like', '%' . $search . '%');
                })
                ->orderBy('id', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);

            if ($testimonial->count() > 0) {
                return [
                    "status" => true,
                    "message" => "Testimonial fetched successfully",
                    "data" => $testimonial
                ];
            } else {
                return [
                    "status" => true,
                    "message" => "No testimonial found",
                    "data" => []
                ];
            }
        } catch (Exception $ex) {
            Log::error("testimonialService  - getTestimonial function error: " . $ex->getMessage());
            return $this->commonService->internalServerErrorResponse(false, "Internal Server Error. Please Contact Admin", []);
        }
    }

    public function getTestimonialById($id)
    {
        try {

            $testimonial = DB::table('testimonials')->where('id', $id)->first();
            if ($testimonial) {
                return [
                    "status" => true,
                    "message" => "Testimonial fetched successfully",
                    "data" => $testimonial
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


    public function updateTestimonial($id, $data)
    {
        try {
            $testimonialInformationExist = DB::table('testimonials')->where('id', $id)->exists();
            if (!$testimonialInformationExist) {
                return [
                    "status" => true,
                    "message" => "Testimonial not found",
                    "data" => []
                ];
            }

            $testimonial = DB::table('testimonials')->where('id', $id)->update($data);
            if ($testimonial) {
                return [
                    "status" => true,
                    "message" => "Testimonial Information updated successfully",
                    "data" => $testimonial
                ];
            } else {
                return [
                    "status" => true,
                    "message" => "Testimonial Information not updated successfully",
                    "data" => []
                ];
            }
        } catch (Exception $ex) {
            Log::error("updateTestimonial function error: " . $ex->getMessage());
            return $this->commonService->internalServerErrorResponse(false, $ex->getMessage(), []);
        }
    }
}
