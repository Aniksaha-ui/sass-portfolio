<?php

namespace App\Repository\Services\Admin\Publication;

use App\Helpers\admin\FileManageHelper;
use App\Repository\Services\Common\CommonService;
use Exception;
use Illuminate\Support\Facades\Log;
use DB;


class PublicationService
{

    private $commonService;
    public function __construct(CommonService $commonService)
    {
        $this->commonService = $commonService;
    }

    public function store($data)
    {
        try {
              if (request()->hasFile('pdf_url')) {
                $documentLink = FileManageHelper::uploadFile('publication', $data['pdf_url']);
                $data['pdf_url'] = $documentLink;
            } else {
                $data['pdf_url'] = 'images/trips/default.png';
            }

        

            $publicationId = DB::table('publications')->insertGetId($data);
            if ($publicationId) {
                return [
                    "status" => true,
                    "message" => "Publications created successfully",
                    "data" => $publicationId
                ];
            } else {
                return [
                    "status" => false,
                    "message" => "Publications not created",
                    "data" => []
                ];
            }
        } catch (Exception $e) {
            Log::error("Publicationservice - store function error: " . $e->getMessage());
            return $this->commonService->internalServerErrorResponse(false, "Internal server error", []);
        }
    }

    public function getPublication($perPage, $page, $search)
    {
        try {
            $publication = DB::table('publications')
                ->where(function ($query) use ($search) {
                    $query->where('name', 'like', '%' . $search . '%')
                        ->orWhere('designation', 'like', '%' . $search . '%')
                        ->orWhere('feedback', 'like', '%' . $search . '%');
                })
                ->orderBy('id', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);

            if ($publication->count() > 0) {
                return [
                    "status" => true,
                    "message" => "Publication fetched successfully",
                    "data" => $publication
                ];
            } else {
                return [
                    "status" => true,
                    "message" => "No publication found",
                    "data" => []
                ];
            }
        } catch (Exception $ex) {
            Log::error("publicationservice  - getPublication function error: " . $ex->getMessage());
            return $this->commonService->internalServerErrorResponse(false, "Internal Server Error. Please Contact Admin", []);
        }
    }

    public function getPublicationById($id)
    {
        try {

            $publication = DB::table('publications')->where('id', $id)->first();
            if ($publication) {
                return [
                    "status" => true,
                    "message" => "Publication fetched successfully",
                    "data" => $publication
                ];
            } else {
                return [
                    "status" => true,
                    "message" => "No publications found",
                    "data" => []
                ];
            }
        } catch (Exception $ex) {
            Log::error("getPublicationById function error: " . $ex->getMessage());
            return $this->commonService->internalServerErrorResponse(false, "Internal Server Error. Please Contact Admin", []);
        }
    }


    public function updatePublication($id, $data)
    {
        try {
            $publicationInformationExist = DB::table('publications')->where('id', $id)->exists();
            if (!$publicationInformationExist) {
                return [
                    "status" => true,
                    "message" => "Publication not found",
                    "data" => []
                ];
            }

            $publication = DB::table('publications')->where('id', $id)->update($data);
            if ($publication) {
                return [
                    "status" => true,
                    "message" => "Publication Information updated successfully",
                    "data" => $publication
                ];
            } else {
                return [
                    "status" => true,
                    "message" => "Publication Information not updated successfully",
                    "data" => []
                ];
            }
        } catch (Exception $ex) {
            Log::error("updatePublication function error: " . $ex->getMessage());
            return $this->commonService->internalServerErrorResponse(false, $ex->getMessage(), []);
        }
    }
}
