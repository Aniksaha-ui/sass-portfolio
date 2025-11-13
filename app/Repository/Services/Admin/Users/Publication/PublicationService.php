<?php

namespace App\Repository\Services\Admin\Users\Publication;

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


    public function getPublication($perPage, $page, $search)
    {
        try {

            $perPage = isset($perPage) ? intval($perPage) : 10;
            $user = DB::table('publications')
                ->where(function ($query) use ($search) {
                    $query->where('title', 'like', '%' . $search . '%')
                        ->orWhere('abstract', 'like', '%' . $search . '%')
                        ->orWhere('authors', 'like', '%' . $search . '%')
                        ->orWhere('doi', 'like', '%' . $search . '%');
                })
                ->paginate($perPage, ['id', 'title', 'abstract', 'publication_date', 'publisher', 'authors', 'doi', 'pdf_url', 'url', 'status'], 'page', $page);
            if ($user->count() > 0) {
                return [
                    "status" => true,
                    "message" => "Publication fetched successfully",
                    "data" => $user
                ];
            } else {
                return [
                    "status" => true,
                    "message" => "No publication found",
                    "data" => []
                ];
            }
        } catch (Exception $ex) {
            Log::error("getPublication function error: " . $ex->getMessage());
            return $this->commonService->internalServerErrorResponse(false, "Internal Server Error. Please Contact Admin", []);
        }
    }
}
