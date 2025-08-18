<?php

namespace App\Http\Controllers\User\Blog;

use App\Http\Controllers\Controller;
use App\Repository\Services\Admin\Blog\BlogService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class UserBlogController extends Controller
{
    private $blogService;
    public function __construct(BlogService $blogService)
    {
        $this->blogService = $blogService;
    }

    public function getAllBlogs(Request $request)
    {
        try {
            $page = $request->query('page') ?? 1;
            $search = $request->query('search') ?? '';
            $perPage = $request->query('perPage') ?? 10;
            $response = $this->blogService->getBlogs($perPage, $page, $search);

            return response()->json([
                "isExecuted" => $response['status'],
                "message" => $response['message'],
                "data" => $response['data']
            ], 200);
        } catch (Exception $ex) {
            Log::info("getAllBlogs function error: " . $ex->getMessage());
        }
    }


    public function getBlogById($id)
    {
        try {
            $response = $this->blogService->getBlogById($id);
            return response()->json([
                "isExecuted" => $response['status'],
                "message" => $response['message'],
                "data" => $response['data']
            ], 200);
        } catch (Exception $ex) {
            Log::info("getBlogById function error: " . $ex->getMessage());
        }
    }
}
