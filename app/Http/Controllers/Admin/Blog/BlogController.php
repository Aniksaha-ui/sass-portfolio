<?php

namespace App\Http\Controllers\Admin\Blog;

use App\Http\Controllers\Controller;
use App\Http\Requests\BlogRequest;
use App\Repository\Services\Admin\Blog\BlogService;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Support\Facades\Log;

class BlogController extends Controller
{

    private $blogService;
    public function __construct(BlogService $usersService)
    {
        $this->blogService = $usersService;
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

    public function addNewBlog(BlogRequest $request)
    {
        try {

            $insertedBlogInformation = $this->blogService->store($request->validated());
            return response()->json([
                "isExecuted" => $insertedBlogInformation['status'],
                "message" => $insertedBlogInformation['message'],
                "data" => $insertedBlogInformation['data']
            ], 200);
        } catch (Exception $ex) {
            Log::info("addNewBlog function error: " . $ex->getMessage());
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

    public function updateBlog($id, BlogRequest $request)
    {
        try {
            $response = $this->blogService->updateBlog($id, $request->validated());
            return response()->json([
                "isExecuted" => $response['status'],
                "message" => $response['message'],
                "data" => $response['data']
            ], 200);
        } catch (Exception $ex) {
            Log::info("updateBlog function error: " . $ex->getMessage());
        }
    }
}
