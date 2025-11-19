<?php

namespace App\Http\Controllers\Admin\Publication;

use App\Http\Controllers\Controller;
use App\Repository\Services\Admin\Publication\PublicationService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminPublicationController  extends Controller
{


     private $publicationService;
    public function __construct(PublicationService $publicationService)
    {
        $this->publicationService = $publicationService;
    }

      public function getAllPublications(Request $request)
    {
        try {
            $page = $request->query('page') ?? 1;
            $search = $request->query('search') ?? '';
            $perPage = $request->query('perPage') ?? 10;
            $response = $this->publicationService->getPublication($perPage, $page, $search);

            return response()->json([
                "isExecuted" => $response['status'],
                "message" => $response['message'],
                "data" => $response['data']
            ], 200);
        } catch (Exception $ex) {
            Log::info("getAllPublication function error: " . $ex->getMessage());
        }
    }

    public function addNewPublication(Request $request)
    {
        try {

            $insertedPublicationInformation = $this->publicationService->store($request->all());
            return response()->json([
                "isExecuted" => $insertedPublicationInformation['status'],
                "message" => $insertedPublicationInformation['message'],
                "data" => $insertedPublicationInformation['data']
            ], 200);
        } catch (Exception $ex) {
            Log::info("addNewPublication function error: " . $ex->getMessage());
        }
    }

    public function getPublicationById($id)
    {
        try {
            $response = $this->publicationService->getPublicationById($id);
            return response()->json([
                "isExecuted" => $response['status'],
                "message" => $response['message'],
                "data" => $response['data']
            ], 200);
        } catch (Exception $ex) {
            Log::info("getPublicationById function error: " . $ex->getMessage());
        }
    }

    public function updatePublication($id, Request $request)
    {
        try {
            $response = $this->publicationService->updatePublication($id, $request->all());
            return response()->json([
                "isExecuted" => $response['status'],
                "message" => $response['message'],
                "data" => $response['data']
            ], 200);
        } catch (Exception $ex) {
            Log::info("updatePublication function error: " . $ex->getMessage());
        }
    }
}
