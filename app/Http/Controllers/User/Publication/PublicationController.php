<?php

namespace App\Http\Controllers\User\Publication;

use App\Http\Controllers\Controller;
use App\Repository\Services\Admin\Users\Publication\PublicationService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PublicationController extends Controller
{
    private $publicationService;
    public function __construct(PublicationService $publicationService)
    {
        $this->publicationService = $publicationService;
    }

    public function getAllPublication(Request $request)
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
}
